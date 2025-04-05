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
    case Stable = 'stable';
    case RC = 'RC';
    case Beta = 'beta';
    case Alpha = 'alpha';
    case Dev = 'dev';

    public static function create(Build $config): static
    {
        return self::tryFrom((string) $config->stability) ?? self::fromGlobals();
    }

    public static function fromGlobals(): self
    {
        return self::Stable;
    }

    public function getWeight(): int
    {
        return match ($this) {
            self::Stable => 4,
            self::RC => 3,
            self::Beta => 2,
            self::Alpha => 1,
            self::Dev => 0,
        };
    }
}
