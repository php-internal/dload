<?php

declare(strict_types=1);

namespace Internal\DLoad\Tests\Unit\Module\Repository\Stub;

use Internal\DLoad\Module\Common\Config\Embed\Repository as RepositoryConfig;
use Internal\DLoad\Module\Repository\Repository;
use Internal\DLoad\Module\Repository\RepositoryFactory;

/**
 * Stub implementation of RepositoryFactory for testing.
 */
final class RepositoryFactoryStub implements RepositoryFactory
{
    private array $supportedTypes;
    private ?Repository $repository;

    /**
     * @param array<string> $supportedTypes Types this factory will support
     * @param Repository|null $repository Repository to return when create() is called
     */
    public function __construct(array $supportedTypes = ['github'], ?Repository $repository = null)
    {
        $this->supportedTypes = $supportedTypes;
        $this->repository = $repository;
    }

    public function supports(RepositoryConfig $config): bool
    {
        return \in_array($config->type, $this->supportedTypes, true);
    }

    public function create(RepositoryConfig $config): Repository
    {
        if ($this->repository === null) {
            return new RepositoryStub($config->uri);
        }

        return $this->repository;
    }
}
