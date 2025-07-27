<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\Velox\Internal\Client;

use Internal\DLoad\Module\Config\Schema\Action\Velox\Plugin;
use Internal\DLoad\Module\HttpClient\Factory;
use Internal\DLoad\Module\Velox\ApiClient as ApiClientInterface;
use Internal\DLoad\Module\Velox\Exception\Api;
use Psr\Http\Message\ResponseInterface;

/**
 * RoadRunner API client implementation.
 *
 * @internal
 */
final class BuildRoadRunner implements ApiClientInterface
{
    private const API_BASE_URL = 'https://build.roadrunner.dev/api/v1';

    public function __construct(
        private readonly Factory $httpFactory,
    ) {}

    public function generateConfig(
        array $plugins,
        ?string $golangVersion = null,
        ?string $binaryVersion = null,
        array $options = [],
    ): string {
        $requestData = [
            'plugins' => \array_map(static fn(Plugin $plugin): string => $plugin->name, $plugins),
            'format' => 'toml',
        ];

        $golangVersion !== null and $requestData['golang_version'] = $golangVersion;
        $binaryVersion !== null and $requestData['binary_version'] = $binaryVersion;
        $options === [] or $requestData = \array_merge($requestData, $options);

        return $this->makeRequest(
            'POST',
            '/plugins/generate-config',
            $requestData,
        );
    }

    public function getAvailablePlugins(?string $search = null): array
    {
        $query = [];
        $search !== null and $query['search'] = $search;

        $response = $this->makeRequest('GET', '/plugins', query: $query);

        return \json_decode($response, true, 512, JSON_THROW_ON_ERROR);
    }

    /**
     * @param non-empty-string $method
     * @param non-empty-string $endpoint
     * @param array<string, mixed> $data
     * @param array<string, string> $query
     * @throws Api
     */
    private function makeRequest(
        string $method,
        string $endpoint,
        array $data = [],
        array $query = [],
    ): string {
        try {
            $uri = $this->httpFactory->uri(self::API_BASE_URL . $endpoint, $query);
            $request = $this->httpFactory->request($method, $uri, [
                'Accept' => 'text/plain',
                'Content-Type' => 'application/json',
            ]);

            if ($data !== []) {
                $body = $this->httpFactory->request('POST', '')->getBody();
                $body->write(\json_encode($data, JSON_THROW_ON_ERROR));
                $request = $request->withBody($body);
            }

            $response = $this->httpFactory->client()->sendRequest($request);

            return $this->handleResponse($response);
        } catch (\Throwable $e) {
            throw new Api("API request failed: {$e->getMessage()}", 0, $e);
        }
    }

    /**
     * @throws Api
     */
    private function handleResponse(ResponseInterface $response): string
    {
        $statusCode = $response->getStatusCode();

        if ($statusCode < 200 || $statusCode >= 300) {
            throw new Api("API request failed with status {$statusCode}: {$response->getBody()->getContents()}");
        }

        return $response->getBody()->getContents();
    }
}
