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
        return self::tryFromString(\PHP_OS_FAMILY) ?? throw new \OutOfRangeException(
            \sprintf(self::ERROR_UNKNOWN_OS, \PHP_OS_FAMILY),
        );
    }

    public static function tryFromString(string $name): ?self
    {
        return match (\strtolower($name)) {
            'windows' => self::Windows,
            'bSD' => self::BSD,
            'darwin' => self::Darwin,
            'linux' => \str_contains(\PHP_OS, 'alpine')
                ? self::Alpine
                : self::Linux,
            default => null,
        };
    }

    public static function tryFromBuildName(string $name): ?self
    {
        if (\preg_match('/\b(windows|linux|darwin|alpine|bsd|freebsd)\b/i', $name, $matches) !== 1) {
            return null;
        }

        return self::tryFromString(\strtolower($matches[1]));
    }
}
