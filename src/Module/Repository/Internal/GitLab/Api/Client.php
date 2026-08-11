<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\Repository\Internal\GitLab\Api;

use Internal\DLoad\Module\Config\Schema\GitLab;
use Internal\DLoad\Module\HttpClient\Factory as HttpFactory;
use Internal\DLoad\Module\HttpClient\Method;
use Internal\DLoad\Module\Repository\Exception\RepositoryException;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\UriInterface;

/**
 * HTTP client wrapper with GitLab-specific error handling and authentication.
 *
 * Converts unsuccessful responses (rate limits, invalid token, missing project, etc.)
 * into exceptions with actionable messages. Adds GitLab API token authentication when available.
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

    private readonly ResponseValidator $validator;

    public function __construct(
        private readonly HttpFactory $httpFactory,
        private readonly ClientInterface $client,
        private readonly GitLab $gitLabConfig,
    ) {
        // Add authorization header if token is available
        $this->gitLabConfig->token !== null and $this->defaultHeaders['authorization'] = 'Bearer ' . $this->gitLabConfig->token;

        $this->validator = new ResponseValidator(authenticated: $this->gitLabConfig->token !== null);
    }

    /**
     * @throws RepositoryException
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
