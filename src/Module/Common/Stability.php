<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\Common;

use Internal\DLoad\Module\Common\Input\Build;
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
