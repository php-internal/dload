<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\Environment;

use Internal\DLoad\Service\Factoriable;

/**
 * Software stability level.
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

    public static function create(): static
    {
        return self::fromGlobals();
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
