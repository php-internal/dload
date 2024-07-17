<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\Environment;

use Internal\DLoad\Service\Factoriable;

/**
 * Architecture enumeration.
 *
 * @internal
 */
enum Architecture: string implements Factoriable
{
    case X86_64 = 'amd64';
    case ARM_64 = 'arm64';

    private const ERROR_UNKNOWN_ARCH = 'Current architecture `%s` may not be supported.';

    public static function create(): static
    {
        return self::fromGlobals();
    }

    public static function fromGlobals(): self
    {
        return match (\php_uname('m')) {
            'AMD64', 'x86', 'x64', 'x86_64' => self::X86_64,
            'arm64', 'aarch64' => self::ARM_64,
            default => throw new \OutOfRangeException(
                \sprintf(
                    self::ERROR_UNKNOWN_ARCH,
                    \php_uname('m'),
                ),
            ),
        };
    }
}
