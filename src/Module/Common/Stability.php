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
 *
 * // Checking if a string is a valid stability
 * $isValid = Stability::isValidStability('beta'); // true
 *
 * // Parsing stability from string with case normalization
 * $stability = Stability::fromString('BETA'); // Stability::Beta
 *
 * // Comparing stability levels
 * $beta = Stability::Beta;
 * $stable = Stability::Stable;
 * $meetRequirement = $stable->meetsMinimum($beta); // true
 * ```
 *
 * @internal
 */
enum Stability: string implements Factoriable
{
    case Stable = 'stable';       // Released version considered stable for production
    case RC = 'RC';               // Almost ready for stable release
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
     * Parse stability from string with case normalization.
     */
    public static function fromString(string $value): ?self
    {
        // Try exact match first
        $stability = self::tryFrom($value);
        if ($stability !== null) {
            return $stability;
        }

        // Try case variations
        foreach (self::cases() as $case) {
            if (\strcasecmp($case->value, $value) === 0) {
                return $case;
            }
        }

        return null;
    }

    /**
     * Compare stability levels for constraint matching.
     * Returns true if this stability meets the minimum requirement.
     */
    public function meetsMinimum(self $minimum): bool
    {
        return $this->getWeight() >= $minimum->getWeight();
    }

    /**
     * Get the numerical weight of this stability level
     * Higher numbers indicate more stable versions
     *
     */
    public function getWeight(): int
    {
        return match ($this) {
            self::Stable => 9,
            self::RC => 8,
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
