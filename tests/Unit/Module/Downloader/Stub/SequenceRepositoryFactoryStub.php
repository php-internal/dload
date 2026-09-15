<?php

declare(strict_types=1);

namespace Internal\DLoad\Tests\Unit\Module\Downloader\Stub;

use Internal\DLoad\Module\Config\Schema\Embed\Repository as RepositoryConfig;
use Internal\DLoad\Module\Repository\Repository;
use Internal\DLoad\Module\Repository\RepositoryFactory;

/**
 * Factory that hands out prepared repositories one after another, so a test can model
 * a repository whose release list changes between two lookups.
 */
final class SequenceRepositoryFactoryStub implements RepositoryFactory
{
    /** @var int<0, max> Number of repositories created so far. */
    public int $created = 0;

    /**
     * @param list<Repository> $repositories Repositories to return, in order; the last one repeats.
     */
    public function __construct(
        private readonly array $repositories,
    ) {}

    public function supports(RepositoryConfig $config): bool
    {
        return true;
    }

    public function create(RepositoryConfig $config): Repository
    {
        $repository = $this->repositories[\min($this->created, \count($this->repositories) - 1)];
        ++$this->created;

        return $repository;
    }
}
