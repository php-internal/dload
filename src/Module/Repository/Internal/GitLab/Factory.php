<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\Repository\Internal\GitLab;

use Internal\DLoad\Module\Config\Schema\Embed\Repository as RepositoryConfig;
use Internal\DLoad\Module\Config\Schema\GitLab;
use Internal\DLoad\Module\HttpClient\Factory as HttpFactory;
use Internal\DLoad\Module\Repository\Internal\GitLab\Api\Client;
use Internal\DLoad\Module\Repository\Internal\GitLab\Api\RepositoryApi;
use Internal\DLoad\Module\Repository\RepositoryFactory;
use Internal\DLoad\Service\Logger;

/**
 * Factory for creating GitLab repository instances.
 *
 * This factory creates instances of {@see GitLabRepository} based on the provided configuration.
 * It checks if the configuration type is 'gitlab' and then initializes the GitLab API client
 * and repository API for the specified organization and repository.
 *
 * @internal
 * @psalm-internal Internal\DLoad\Module\Repository
 */
final class Factory implements RepositoryFactory
{
    private readonly Client $gitLabClient;

    public function __construct(
        private readonly HttpFactory $httpFactory,
        GitLab $gitLabConfig,
        private readonly Logger $logger,
    ) {
        $this->gitLabClient = new Client(
            $httpFactory,
            $httpFactory->client(),
            $gitLabConfig,
        );
    }

    public function supports(RepositoryConfig $config): bool
    {
        return \strtolower($config->type) === 'gitlab';
    }

    public function create(RepositoryConfig $config): GitLabRepository
    {
        $uri = \parse_url($config->uri, PHP_URL_PATH) ?? $config->uri;
        $api = $this->createRepositoryApi($uri);

        return new GitLabRepository($api, $uri, $this->logger);
    }

    /**
     * @param non-empty-string $projectPath
     */
    private function createRepositoryApi(string $projectPath): RepositoryApi
    {
        return new RepositoryApi($this->gitLabClient, $this->httpFactory, $projectPath);
    }
}
