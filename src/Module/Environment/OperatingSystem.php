<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\Environment;

use Internal\DLoad\Service\Factoriable;

/**
 * Operating system enumeration.
 *
 * @internal
 */
enum OperatingSystem: string implements Factoriable
{
    case Darwin = 'darwin';
    case BSD = 'freebsd';
    case Linux = 'linux';
    case Windows = 'windows';
    case Alpine = 'unknown-musl';

    private const ERROR_UNKNOWN_OS = 'Current OS `%s` may not be supported';

    public static function create(): static
    {
        return self::fromGlobals();
    }

    public static function fromGlobals(): self
    {
        return match (\PHP_OS_FAMILY) {
            'Windows' => self::Windows,
            'BSD' => self::BSD,
            'Darwin' => self::Darwin,
            'Linux' => \str_contains(\PHP_OS, 'Alpine')
                ? self::Alpine
                : self::Linux,
            default => throw new \OutOfRangeException(\sprintf(self::ERROR_UNKNOWN_OS, \PHP_OS_FAMILY)),
        };
    }
}
