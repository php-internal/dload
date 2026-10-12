<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\Repository;

use Internal\DLoad\Module\Config\Schema\Embed\Repository as RepositoryConfig;
use Internal\DLoad\Module\Repository\Exception\InvalidRepositoryConfigException;

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
     * @throws InvalidRepositoryConfigException If the config does not address a repository
     */
    public function create(RepositoryConfig $config): Repository;
}
