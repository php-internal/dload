<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\Repository\Internal\GitHub\Api;

use Internal\DLoad\Module\HttpClient\Factory as HttpFactory;
use Internal\DLoad\Module\HttpClient\Method;
use Internal\DLoad\Module\Repository\Exception\RepositoryException;
use Internal\DLoad\Module\Repository\Internal\ApiToken;
use Internal\DLoad\Module\Repository\Internal\Server;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\UriInterface;

/**
 * HTTP client wrapper with GitHub-specific error handling and authentication.
 *
 * Converts unsuccessful responses (rate limits, invalid token, missing repository, etc.)
 * into exceptions with actionable messages. Adds the GitHub API token to requests bound for
 * the server the client works with.
 *
 * @internal
 * @psalm-internal Internal\DLoad\Module\Repository\Internal\GitHub
 */
final class Client
{
    /**
     * Hosts of the public GitHub the token may be sent to. Asset URLs come from the API response
     * and from the version registry on disk, so a tampered file must not be able to point
     * a request with the token at a host of its choosing.
     */
    private const TRUSTED_HOSTS = ['github.com', 'githubusercontent.com'];

    /**
     * @var array<non-empty-string, non-empty-string>
     */
    private array $defaultHeaders = [
        'accept' => 'application/vnd.github.v3+json',
    ];

    private readonly ResponseValidator $validator;

    /**
     * @param Server|null $server GitHub Enterprise Server instance, null for the public GitHub.
     */
    public function __construct(
        private readonly HttpFactory $httpFactory,
        private readonly ClientInterface $client,
        private readonly ?ApiToken $token = null,
        private readonly ?Server $server = null,
    ) {
        $this->validator = new ResponseValidator(
            authenticated: $this->token !== null,
            tokenVariable: $this->token?->variable ?? $this->server?->tokenVariable(),
        );
    }

    /**
     * @param Method|non-empty-string $method
     * @param array<string, string> $headers
     * @throws RepositoryException
     */
    public function request(Method|string $method, string|UriInterface $uri, array $headers = []): ResponseInterface
    {
        $headers += $this->defaultHeaders;
        $this->token !== null && $this->isTrusted($uri)
            and $headers += ['authorization' => 'Bearer ' . $this->token->value];

        return $this->sendRequest($this->httpFactory->request($method, $uri, $headers));
    }

    /**
     * @throws RepositoryException
     */
    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        try {
            $response = $this->client->sendRequest($request);
        } catch (ClientExceptionInterface $e) {
            throw $this->validator->transportFailure($request, $e);
        }

        $this->validator->validate($request, $response);

        return $response;
    }

    private function isTrusted(string|UriInterface $uri): bool
    {
        if ($this->server !== null) {
            return $this->server->isSecure() && $this->server->serves($uri);
        }

        [$scheme, $host] = Server::split($uri);
        if ($host === '' || $scheme !== 'https') {
            return false;
        }

        foreach (self::TRUSTED_HOSTS as $trusted) {
            if ($host === $trusted || \str_ends_with($host, '.' . $trusted)) {
                return true;
            }
        }

        return false;
    }
}
