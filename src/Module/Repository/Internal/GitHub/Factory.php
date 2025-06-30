<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\Repository\Internal\GitHub;

use Internal\DLoad\Module\Config\Schema\Embed\Repository as RepositoryConfig;
use Internal\DLoad\Module\Config\Schema\GitHub;
use Internal\DLoad\Module\HttpClient\Factory as HttpFactory;
use Internal\DLoad\Module\Repository\Internal\GitHub\Api\Client;
use Internal\DLoad\Module\Repository\Internal\GitHub\Api\RepositoryApi;
use Internal\DLoad\Module\Repository\RepositoryFactory;

/**
 * Factory for creating GitHub repository instances.
 *
 * This factory creates instances of {@see GitHubRepository} based on the provided configuration.
 * It checks if the configuration type is 'github' and then initializes the GitHub API client
 * and repository API for the specified organization and repository.
 *
 * @internal
 * @psalm-internal Internal\DLoad\Module\Repository
 */
final class Factory implements RepositoryFactory
{
    private readonly Client $gitHubClient;

    public function __construct(
        private readonly HttpFactory $httpFactory,
        GitHub $gitHubConfig,
    ) {
        $this->gitHubClient = new Client(
            $httpFactory,
            $httpFactory->client(),
            $gitHubConfig,
        );
    }

    public function supports(RepositoryConfig $config): bool
    {
        return \strtolower($config->type) === 'github';
    }

    public function create(RepositoryConfig $config): GitHubRepository
    {
        $uri = \parse_url($config->uri, PHP_URL_PATH) ?? $config->uri;
        [$org, $repo] = \array_slice(\explode('/', $uri), -2);

        $api = $this->createRepositoryApi($org, $repo);

        return new GitHubRepository($api, $org, $repo);
    }

    /**
     * @param non-empty-string $owner
     * @param non-empty-string $repo
     */
    private function createRepositoryApi(string $owner, string $repo): RepositoryApi
    {
        return new RepositoryApi($this->gitHubClient, $this->httpFactory, $owner, $repo);
    }
}
