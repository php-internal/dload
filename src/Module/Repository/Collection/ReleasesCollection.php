<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\Repository\Collection;

use Internal\DLoad\Module\Common\Stability;
use Internal\DLoad\Module\Repository\Internal\Collection;
use Internal\DLoad\Module\Repository\ReleaseInterface;

/**
 * Collection of software releases with filtering and sorting capabilities.
 *
 * Provides methods to filter releases by version constraints, stability levels,
 * and availability of assets.
 *
 * ```php
 * // Get stable releases that satisfy a version constraint
 * $releases = $repository->getReleases()
 *     ->stable()
 *     ->satisfies('^2.0.0')
 *     ->withAssets()
 *     ->sortByVersion();
 *
 * // Get the most recent release
 * $latestRelease = $releases->first();
 * ```
 *
 * @template-extends Collection<ReleaseInterface>
 * @internal
 * @psalm-internal Internal\DLoad\Module
 */
final class ReleasesCollection extends Collection
{
    /**
     * Filters releases to those that satisfy the given version constraint.
     *
     * @param non-empty-string $constraint Version constraint in Composer format
     * @return $this New filtered collection
     */
    public function satisfies(string $constraint): self
    {
        return $this->filter(static fn(ReleaseInterface $r): bool => $r->satisfies($constraint));
    }

    /**
     * Filters releases to those that do not satisfy the given version constraint.
     *
     * @param non-empty-string $constraint Version constraint in Composer format
     * @return $this New filtered collection
     */
    public function notSatisfies(string $constraint): self
    {
        return $this->except(static fn(ReleaseInterface $r): bool => $r->satisfies($constraint));
    }

    /**
     * Filters releases to those that have at least one asset.
     *
     * @return $this New filtered collection
     */
    public function withAssets(): self
    {
        return $this->filter(
            static fn(ReleaseInterface $r): bool => !$r->getAssets()
                ->empty(),
        );
    }

    /**
     * Sorts releases by version in descending order (newest first) and maintain index association.
     *
     * @return $this New sorted collection
     */
    public function sortByVersion(): self
    {
        $result = $this->items;

        $sort = function (ReleaseInterface $a, ReleaseInterface $b): int {
            return \version_compare($this->comparisonVersionString($b), $this->comparisonVersionString($a));
        };

        \uasort($result, $sort);

        return new self($result);
    }

    /**
     * Filters releases to only include stable versions.
     *
     * @return $this New filtered collection
     */
    public function stable(): self
    {
        return $this->stability(Stability::Stable);
    }

    /**
     * Filters releases to include only those matching the exact stability level.
     *
     * @param Stability $stability Required stability level
     * @return $this New filtered collection
     */
    public function stability(Stability $stability): self
    {
        return $this->filter(static fn(ReleaseInterface $rel): bool => $rel->getStability() === $stability);
    }

    /**
     * Filters releases to include those with at least the specified minimum stability.
     *
     * @param Stability $stability Minimum stability level
     * @return $this New filtered collection
     */
    public function minimumStability(Stability $stability): self
    {
        $weight = $stability->getWeight();
        return $this->filter(
            static fn(ReleaseInterface $release): bool => $release->getStability()->getWeight() >= $weight,
        );
    }

    /**
     * Converts version string to a format suitable for comparison.
     *
     * Normalizes version strings to handle stability suffixes properly.
     *
     * @return non-empty-string Normalized version string
     */
    private function comparisonVersionString(ReleaseInterface $release): string
    {
        $stability = $release->getStability();

        return \ltrim(\str_replace(
            '-' . $stability->value,
            '.' . $stability->getWeight() . '.',
            $release->getVersion(),
        ), 'v');
    }
}
