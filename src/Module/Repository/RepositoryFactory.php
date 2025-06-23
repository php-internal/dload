<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\Repository;

use Internal\DLoad\Module\Config\Schema\Embed\Repository as RepositoryConfig;

/**
 * Interface for repository factory implementations.
 *
 * Each repository type (GitHub, GitLab, etc.) should have its own factory
 * implementation that creates repository instances from configuration.
 */
interface RepositoryFactory
{
    /**
     * Determines if this factory can create a repository for the given config.
     */
    public function supports(RepositoryConfig $config): bool;

    /**
     * Creates a repository instance from the given config.
     *
     * @param RepositoryConfig $config Repository configuration
     * @return Repository Created repository instance
     * @throws \RuntimeException If repository cannot be created
     */
    public function create(RepositoryConfig $config): Repository;
}
