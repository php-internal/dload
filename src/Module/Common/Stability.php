<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\Common;

use Internal\DLoad\Module\Common\Input\Build;
use Internal\DLoad\Service\Factoriable;

/**
 * Software stability level.
 *
 * Defines the stability level of software releases from most stable to least.
 * Used to filter releases based on desired stability.
 *
 * ```php
 * // Getting stability from build config
 * $stability = Stability::create($buildConfig);
 *
 * // Getting the stability level weight (higher number means more stable)
 * $weight = $stability->getWeight();
 * ```
 *
 * @internal
 */
enum Stability: string implements Factoriable
{
    case Stable = 'stable';       // Released version considered stable for production
    case RC = 'RC';               // Almost ready for stable release
    case Priority = 'priority';   // High priority pre-release
    case Pre = 'pre';             // Close to final
    case Beta = 'beta';           // Feature complete but may have bugs
    case Preview = 'preview';     // Demonstrates functionality but incomplete
    case Alpha = 'alpha';         // Early testing, incomplete features
    case Unstable = 'unstable';   // May crash or have major issues
    case Dev = 'dev';             // Active development
    case Snapshot = 'snapshot';   // Point-in-time version of current development
    case Nightly = 'nightly';     // Automatically generated daily build

    /**
     * Factory method to create a Stability instance from a Build configuration
     */
    public static function create(Build $config): static
    {
        return self::tryFrom((string) $config->stability) ?? self::fromGlobals();
    }

    /**
     * Provides a default Stability value if none is specified
     */
    public static function fromGlobals(): self
    {
        return self::Stable;
    }

    /**
     * Get the numerical weight of this stability level
     * Higher numbers indicate more stable versions
     *
     * @return int<0, 10> The weight/priority of this stability level (0-9)
     */
    public function getWeight(): int
    {
        return match ($this) {
            self::Stable => 10,
            self::RC => 9,
            self::Priority => 8,
            self::Pre => 7,
            self::Beta => 6,
            self::Preview => 5,
            self::Alpha => 4,
            self::Unstable => 3,
            self::Dev => 2,
            self::Snapshot => 1,
            self::Nightly => 0,
        };
    }
}
