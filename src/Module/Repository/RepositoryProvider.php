<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\Repository;

use Internal\DLoad\Module\Common\Config\Embed\Repository as RepositoryConfig;

/**
 * Factory service for creating repository instances from configuration.
 *
 * Uses registered repository factories to create appropriate repository
 * implementations based on the repository type.
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
    /** @var RepositoryFactory[] $factories */
    private array $factories = [];

    /**
     * Adds a repository factory to the provider.
     */
    public function addRepositoryFactory(RepositoryFactory $factory): self
    {
        $this->factories[] = $factory;
        return $this;
    }

    /**
     * Creates a repository instance based on the provided configuration.
     *
     * @param RepositoryConfig $config Repository configuration
     * @return Repository Created repository instance
     * @throws \RuntimeException When no factory supports the repository type
     */
    public function getByConfig(RepositoryConfig $config): Repository
    {
        foreach ($this->factories as $factory) {
            if ($factory->supports($config)) {
                return $factory->create($config);
            }
        }

        throw new \RuntimeException("No factory found for repository type `$config->type`.");
    }
}
