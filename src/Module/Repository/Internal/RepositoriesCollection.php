<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\Repository\Internal;

use Internal\DLoad\Module\Repository\RepositoryInterface;

/**
 * @internal
 * @psalm-internal Internal\DLoad\Module\Repository
 */
class RepositoriesCollection implements RepositoryInterface
{
    /**
     * @var array<RepositoryInterface>
     */
    private array $repositories;

    /**
     * @param array<RepositoryInterface> $repositories
     */
    public function __construct(array $repositories)
    {
        $this->repositories = $repositories;
    }

    /**
     * @return non-empty-string
     */
    public function getName(): string
    {
        return 'unknown/unknown';
    }

    public function getReleases(): ReleasesCollection
    {
        return ReleasesCollection::from(function () {
            foreach ($this->repositories as $repository) {
                yield from $repository->getReleases();
            }
        });
    }
}
