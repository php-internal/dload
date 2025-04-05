<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\Repository;

use Internal\DLoad\Module\Repository\Collection\ReleasesCollection;

/**
 * Represents a software repository that contains releases.
 *
 * This interface defines the contract for accessing repository information
 * and retrieving all available releases.
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
