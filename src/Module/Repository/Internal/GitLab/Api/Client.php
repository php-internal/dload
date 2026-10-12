<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\Repository\Internal\GitLab\Api;

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
 * HTTP client wrapper with GitLab-specific error handling and authentication.
 *
 * Converts unsuccessful responses (rate limits, invalid token, missing project, etc.)
 * into exceptions with actionable messages. Adds the GitLab API token to requests bound for
 * the server the client works with.
 *
 * @internal
 * @psalm-internal Internal\DLoad\Module\Repository\Internal\GitLab
 */
final class Client
{
    public const PUBLIC_SERVER = 'https://gitlab.com';

    /**
     * @var array<non-empty-string, non-empty-string>
     */
    private array $defaultHeaders = [
        'accept' => 'application/json',
    ];

    private readonly ResponseValidator $validator;
    private readonly Server $server;

    /**
     * @param Server|null $server Self-hosted GitLab instance, null for the public GitLab.
     */
    public function __construct(
        private readonly HttpFactory $httpFactory,
        private readonly ClientInterface $client,
        private readonly ?ApiToken $token = null,
        ?Server $server = null,
    ) {
        $this->server = $server ?? Server::fromString(self::PUBLIC_SERVER);
        $this->validator = new ResponseValidator(
            authenticated: $this->token !== null,
            tokenVariable: $this->token?->variable ?? $server?->tokenVariable(),
        );
    }

    /**
     * @throws RepositoryException
     */
    public function downloadArtifact(string|UriInterface $uri): ResponseInterface
    {
        $headers = $this->isTrusted($uri) ? ['PRIVATE-TOKEN' => $this->token->value] : [];

        return $this->sendRequest($this->httpFactory->request(Method::Get, $uri, $headers));
    }

    /**
     * @param Method|non-empty-string $method
     * @param array<string, string> $headers
     * @throws RepositoryException
     */
    public function request(Method|string $method, string|UriInterface $uri, array $headers = []): ResponseInterface
    {
        $headers += $this->defaultHeaders;
        $this->isTrusted($uri) and $headers += ['authorization' => 'Bearer ' . $this->token->value];

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

    /**
     * @psalm-assert-if-true !null $this->token
     */
    private function isTrusted(string|UriInterface $uri): bool
    {
        return $this->token !== null && $this->server->isSecure() && $this->server->serves($uri);
    }
}
