<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\Repository;

use Internal\DLoad\Module\Common\Stability;
use Internal\DLoad\Module\Common\VersionConstraint;
use Internal\DLoad\Module\Repository\Collection\AssetsCollection;

/**
 * Represents a single release of software from a repository.
 *
 * A release contains information about its version, stability and associated assets
 * that can be downloaded. Release objects provide access to all distributable files
 * for a particular software version.
 *
 * ```php
 * $releases = $repository->getReleases()
 *     ->minimumStability(Stability::Stable)
 *     ->satisfies('^2.0.0')
 *     ->sortByVersion();
 *
 * $latestRelease = $releases->first();
 * $assets = $latestRelease->getAssets();
 * ```
 */
interface ReleaseInterface
{
    /**
     * Returns the repository this release belongs to.
     *
     * @return Repository The parent repository
     */
    public function getRepository(): Repository;

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
     * Uses Composer's version comparison logic to determine if this release
     * satisfies the specified constraint.
     *
     * @param string $constraint Version constraint in Composer format (e.g. "^1.0", ">2.5")
     * @return bool True if the release satisfies the constraint
     */
    public function satisfies(string $constraint): bool;

    /**
     * Checks if this release satisfies the given version constraint DTO.
     *
     * Handles complex constraints including feature suffixes and stability requirements.
     * This method provides enhanced constraint matching beyond basic Composer semver.
     *
     * @param VersionConstraint $constraint Parsed version constraint with suffix and stability info
     * @return bool True if the release satisfies all constraint requirements
     */
    public function satisfiesConstraint(VersionConstraint $constraint): bool;
}
