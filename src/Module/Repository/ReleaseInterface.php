<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\Repository;

use Internal\DLoad\Module\Common\Stability;
use Internal\DLoad\Module\Repository\Collection\AssetsCollection;

/**
 * Represents a single release of software from a repository.
 *
 * A release contains information about its version, stability and associated assets
 * that can be downloaded.
 */
interface ReleaseInterface
{
    /**
     * Returns the repository this release belongs to.
     *
     * @return RepositoryInterface The parent repository
     */
    public function getRepository(): RepositoryInterface;

    /**
     * Returns Composer's compatible "pretty" release version.
     *
     * This version is formatted for semantic versioning compatibility.
     *
     * @return non-empty-string Formatted version string (e.g. "1.2.3")
     */
    public function getName(): string;

    /**
     * Returns internal release tag version.
     *
     * This version string may include prefixes or suffixes that aren't
     * compatible with Composer's comparators.
     *
     * @note This version may not be compatible with Composer's comparators
     *
     * @return non-empty-string Raw version string (e.g. "v1.2.3-beta")
     */
    public function getVersion(): string;

    /**
     * Returns the stability level of this release.
     *
     * @return Stability Enum representing the stability level (Stable, RC, Beta, etc.)
     */
    public function getStability(): Stability;

    /**
     * Returns all assets associated with this release.
     *
     * @return AssetsCollection Collection of downloadable assets
     */
    public function getAssets(): AssetsCollection;

    /**
     * Checks if this release satisfies the given version constraint.
     *
     * @param string $constraint Version constraint in Composer format (e.g. "^1.0", ">2.5")
     * @return bool True if the release satisfies the constraint
     */
    public function satisfies(string $constraint): bool;
}
