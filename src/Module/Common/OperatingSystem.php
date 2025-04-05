<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\Common;

use Internal\DLoad\Module\Common\Input\Build;
use Internal\DLoad\Service\Factoriable;

/**
 * Operating system enumeration.
 *
 * Represents the different operating systems supported by the downloader.
 *
 * ```php
 * // Recommended: Get from container (autowired with build config)
 * $os = $container->get(OperatingSystem::class);
 *
 * // Or create from build config name
 * $os = OperatingSystem::tryFromBuildName('my-software_darwin');
 * ```
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

    public static function create(Build $config): static
    {
        return self::tryFrom((string) $config->os) ?? self::fromGlobals();
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
            'windows', 'win32', 'win64' => self::Windows,
            'bsd', 'freebsd' => self::BSD,
            'darwin' => self::Darwin,
            'linux' => \str_contains(\PHP_OS, 'alpine')
                ? self::Alpine
                : self::Linux,
            default => null,
        };
    }

    public static function tryFromBuildName(string $name): ?self
    {
        return \preg_match(
            '/(?:\b|_)(windows|linux|darwin|alpine|bsd|freebsd|win32|win64)(?:\b|_)/i',
            $name,
            $matches,
        ) === 1
            ? self::tryFromString(\strtolower($matches[1]))
            : null;
    }
}
