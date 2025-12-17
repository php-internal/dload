<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\Repository\Internal\GitLab\Api;

use Internal\DLoad\Module\Config\Schema\GitLab;
use Internal\DLoad\Module\HttpClient\Factory as HttpFactory;
use Internal\DLoad\Module\HttpClient\Method;
use Internal\DLoad\Module\Repository\Internal\GitLab\Exception\GitLabRateLimitException;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\UriInterface;

/**
 * HTTP client wrapper with GitLab-specific error handling and authentication.
 *
 * Detects and handles GitLab Rate Limit responses automatically.
 * Adds GitLab API token authentication when available.
 *
 * @internal
 * @psalm-internal Internal\DLoad\Module\Repository\Internal\GitLab
 */
final class Client
{
    /**
     * @var array<non-empty-string, non-empty-string>
     */
    private array $defaultHeaders = [
        'accept' => 'application/json',
    ];

    public function __construct(
        private readonly HttpFactory $httpFactory,
        private readonly ClientInterface $client,
        private readonly GitLab $gitLabConfig,
    ) {
        // Add authorization header if token is available
        $this->gitLabConfig->token !== null and $this->defaultHeaders['authorization'] = 'Bearer ' . $this->gitLabConfig->token;
    }

    /**
     * @throws GitLabRateLimitException
     * @throws ClientExceptionInterface
     */
    public function downloadArtifact(string|UriInterface $uri): ResponseInterface
    {
        $headers = [];
        if ($this->gitLabConfig->token !== null) {
            $headers = [
                'PRIVATE-TOKEN' =>  $this->gitLabConfig->token,
            ];
        }

        $request = $this->httpFactory->request(Method::Get, $uri, $headers);

        return $this->sendRequest($request);
    }

    /**
     * @param Method|non-empty-string $method
     * @param array<string, string> $headers
     * @throws GitLabRateLimitException
     * @throws ClientExceptionInterface
     */
    public function request(Method|string $method, string|UriInterface $uri, array $headers = []): ResponseInterface
    {
        $request = $this->httpFactory->request($method, $uri, $headers + $this->defaultHeaders);

        return $this->sendRequest($request);
    }

    /**
     * @throws GitLabRateLimitException
     * @throws ClientExceptionInterface
     */
    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $response = $this->client->sendRequest($request);

        if ($response->getStatusCode() === 429) {
            throw new GitLabRateLimitException();
        }

        return $response;
    }
}
