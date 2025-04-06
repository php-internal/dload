<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\Repository\Collection;

use Internal\DLoad\Module\Repository\RepositoryInterface;

/**
 * Collection of repositories that also implements the repository interface.
 *
 * This allows treating multiple repositories as a single virtual repository,
 * aggregating all their releases.
 *
 * ```php
 * // Create a collection of repositories
 * $collection = new RepositoriesCollection([$repo1, $repo2]);
 *
 * // Get all releases from all repositories
 * $allReleases = $collection->getReleases();
 * ```
 *
 * @internal
 * @psalm-internal Internal\DLoad\Module
 */
final class CompositeRepository implements RepositoryInterface
{
    /**
     * @var array<RepositoryInterface>
     */
    private array $repositories;

    /**
     * @param array<RepositoryInterface> $repositories List of repositories to include
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

    /**
     * Returns a collection of all releases from all repositories.
     *
     * @return ReleasesCollection Combined collection of releases
     */
    public function getReleases(): ReleasesCollection
    {
        return ReleasesCollection::from(function () {
            foreach ($this->repositories as $repository) {
                yield from $repository->getReleases();
            }
        });
    }
}
