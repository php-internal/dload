<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\Repository;

use Internal\DLoad\Module\Repository\Collection\ReleasesCollection;

/**
 * Represents a software repository that contains releases.
 *
 * Defines the contract for accessing repository information and retrieving all available releases.
 * Repositories act as the primary source for software packages that can be downloaded.
 *
 * ```php
 * $repository = $repositoryProvider->getByConfig($repoConfig);
 * $releases = $repository->getReleases();
 * $filteredReleases = $releases->satisfies('^2.0.0')->sortByVersion();
 * ```
 */
interface RepositoryInterface
{
    /**
     * Returns the unique identifier of the repository.
     *
     * @return non-empty-string Repository identifier, typically in vendor/package format
     */
    public function getName(): string;

    /**
     * Retrieves all available releases from this repository.
     *
     * @return ReleasesCollection Collection of releases available in this repository
     */
    public function getReleases(): ReleasesCollection;
}
