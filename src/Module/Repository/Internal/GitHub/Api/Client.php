<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\Repository\Internal\GitHub\Api;

use Internal\DLoad\Module\Config\Schema\GitHub;
use Internal\DLoad\Module\HttpClient\Factory as HttpFactory;
use Internal\DLoad\Module\HttpClient\Method;
use Internal\DLoad\Module\Repository\Exception\RepositoryException;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\UriInterface;

/**
 * HTTP client wrapper with GitHub-specific error handling and authentication.
 *
 * Converts unsuccessful responses (rate limits, invalid token, missing repository, etc.)
 * into exceptions with actionable messages. Adds the GitHub API token to requests bound for GitHub hosts.
 *
 * @internal
 * @psalm-internal Internal\DLoad\Module\Repository\Internal\GitHub
 */
final class Client
{
    /**
     * Hosts the token may be sent to. Asset URLs come from the API response and from the version
     * registry on disk, so a tampered file must not be able to point a request with the token at
     * a host of its choosing.
     */
    private const TRUSTED_HOSTS = ['github.com', 'githubusercontent.com'];

    /**
     * @var array<non-empty-string, non-empty-string>
     */
    private array $defaultHeaders = [
        'accept' => 'application/vnd.github.v3+json',
    ];

    private readonly ResponseValidator $validator;

    public function __construct(
        private readonly HttpFactory $httpFactory,
        private readonly ClientInterface $client,
        private readonly GitHub $gitHubConfig,
    ) {
        $this->validator = new ResponseValidator(authenticated: $this->gitHubConfig->token !== null);
    }

    /**
     * @param Method|non-empty-string $method
     * @param array<string, string> $headers
     * @throws RepositoryException
     */
    public function request(Method|string $method, string|UriInterface $uri, array $headers = []): ResponseInterface
    {
        $headers += $this->defaultHeaders;
        $this->gitHubConfig->token !== null && self::isTrusted($uri)
            and $headers += ['authorization' => 'Bearer ' . $this->gitHubConfig->token];

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

    private static function isTrusted(string|UriInterface $uri): bool
    {
        $host = $uri instanceof UriInterface ? $uri->getHost() : \parse_url($uri, \PHP_URL_HOST);
        if (!\is_string($host) || $host === '') {
            return false;
        }

        $host = \strtolower($host);
        foreach (self::TRUSTED_HOSTS as $trusted) {
            if ($host === $trusted || \str_ends_with($host, '.' . $trusted)) {
                return true;
            }
        }

        return false;
    }
}
