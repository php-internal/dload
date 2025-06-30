<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\Repository\Internal\GitHub\Api;

use Internal\DLoad\Module\Config\Schema\GitHub;
use Internal\DLoad\Module\HttpClient\Factory as HttpFactory;
use Internal\DLoad\Module\HttpClient\Method;
use Internal\DLoad\Module\Repository\Internal\GitHub\Exception\GitHubRateLimitException;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\UriInterface;

/**
 * HTTP client wrapper with GitHub-specific error handling and authentication.
 *
 * Detects and handles GitHub Rate Limit responses automatically.
 * Adds GitHub API token authentication when available.
 *
 * @internal
 * @psalm-internal Internal\DLoad\Module\Repository\Internal\GitHub
 */
final class Client
{
    /**
     * @var array<non-empty-string, non-empty-string>
     */
    private array $defaultHeaders = [
        'accept' => 'application/vnd.github.v3+json',
    ];

    public function __construct(
        private readonly HttpFactory $httpFactory,
        private readonly ClientInterface $client,
        private readonly GitHub $gitHubConfig,
    ) {
        // Add authorization header if token is available
        $this->gitHubConfig->token !== null and $this->defaultHeaders['authorization'] = 'Bearer ' . $this->gitHubConfig->token;
    }

    /**
     * @param Method|non-empty-string $method
     * @param array<string, string> $headers
     * @throws GitHubRateLimitException
     * @throws ClientExceptionInterface
     */
    public function request(Method|string $method, string|UriInterface $uri, array $headers = []): ResponseInterface
    {
        $request = $this->httpFactory->request($method, $uri, $headers + $this->defaultHeaders);

        return $this->sendRequest($request);
    }

    /**
     * @throws GitHubRateLimitException
     * @throws ClientExceptionInterface
     */
    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $response = $this->client->sendRequest($request);

        $this->checkForRateLimit($response);

        return $response;
    }

    /**
     * @throws GitHubRateLimitException
     */
    private function checkForRateLimit(ResponseInterface $response): void
    {
        // GitHub rate limit responses typically have 403 status
        if ($response->getStatusCode() !== 403) {
            return;
        }

        $body = $response->getBody()->__toString();

        try {
            /** @var mixed $decoded */
            $decoded = \json_decode($body, true, 512, JSON_THROW_ON_ERROR);

            // GitHub rate limit responses have format: ["API rate limit ...", "https://docs.github.com/..."]
            if (\is_array($decoded)
                && \count($decoded) === 2
                && \is_string($decoded[0])
                && \is_string($decoded[1])
                && \str_contains($decoded[0], 'API rate limit')
            ) {
                throw GitHubRateLimitException::fromApiResponse($decoded);
            }
        } catch (\JsonException) {
            // Not a JSON response, continue without rate limit check
        }
    }
}
