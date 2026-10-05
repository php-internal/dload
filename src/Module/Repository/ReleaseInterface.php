<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\Repository;

use Internal\DLoad\Module\Common\Stability;
use Internal\DLoad\Module\Repository\Collection\AssetsCollection;
use Internal\DLoad\Module\Version\Constraint;
use Internal\DLoad\Module\Version\Version;

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
     * Returns the human-readable release name.
     *
     * @return non-empty-string
     */
    public function getName(): string;

    /**
     * Returns the tag the release was made from, exactly as the repository reports it.
     *
     * Unlike the version, it keeps the configured tag prefix: it identifies the release
     * within the repository and in the version registry.
     *
     * @return non-empty-string
     */
    public function getTag(): string;

    /**
     * Returns the version of this release, parsed from the tag without the tag prefix.
     */
    public function getVersion(): Version;

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
     * @param Constraint $constraint Version constraint
     * @return bool True if the release satisfies the constraint
     */
    public function satisfies(Constraint $constraint): bool;
}
