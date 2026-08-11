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
 * into exceptions with actionable messages. Adds GitHub API token authentication when available.
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

    private readonly ResponseValidator $validator;

    public function __construct(
        private readonly HttpFactory $httpFactory,
        private readonly ClientInterface $client,
        private readonly GitHub $gitHubConfig,
    ) {
        // Add authorization header if token is available
        $this->gitHubConfig->token !== null and $this->defaultHeaders['authorization'] = 'Bearer ' . $this->gitHubConfig->token;

        $this->validator = new ResponseValidator(authenticated: $this->gitHubConfig->token !== null);
    }

    /**
     * @param Method|non-empty-string $method
     * @param array<string, string> $headers
     * @throws RepositoryException
     */
    public function request(Method|string $method, string|UriInterface $uri, array $headers = []): ResponseInterface
    {
        $request = $this->httpFactory->request($method, $uri, $headers + $this->defaultHeaders);

        return $this->sendRequest($request);
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
}
