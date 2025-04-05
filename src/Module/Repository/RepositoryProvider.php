<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\Repository;

use Internal\DLoad\Module\Common\Config\Embed\Repository;
use Internal\DLoad\Module\Repository\Internal\GitHub\Factory as GithubFactory;

/**
 * Factory service for creating repository instances from configuration.
 *
 * Handles the creation of appropriate repository implementations based on
 * the repository type specified in the configuration.
 *
 * ```php
 * // Get the RepositoryProvider service
 * $provider = $container->get(RepositoryProvider::class);
 *
 * // Create a repository instance from configuration
 * $config = new Repository('github', 'vendor/package');
 * $repository = $provider->getByConfig($config);
 * ```
 *
 * @internal
 */
final class RepositoryProvider
{
    /**
     * @param GithubFactory $githubFactory Factory for creating GitHub repository instances
     */
    public function __construct(
        private readonly GithubFactory $githubFactory,
    ) {}

    /**
     * Creates a repository instance based on the provided configuration.
     *
     * @param Repository $config Repository configuration
     * @return RepositoryInterface Created repository instance
     * @throws \RuntimeException When an unknown repository type is specified
     */
    public function getByConfig(Repository $config): RepositoryInterface
    {
        return match (\strtolower($config->type)) {
            'github' => $this->githubFactory->create($config->uri),
            default => throw new \RuntimeException("Unknown repository type `$config->type`."),
        };
    }
}
