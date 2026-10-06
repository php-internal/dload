<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\Common;

use Internal\Container\Factoriable;
use Internal\DLoad\Module\Common\Input\Build;

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
    case Android = 'android';

    private const ERROR_UNKNOWN_OS = 'Current OS `%s` may not be supported';

    public static function create(Build $config): static
    {
        return self::tryFrom((string) $config->os)
            ?? self::tryFromString((string) $config->os)
            ?? self::fromGlobals();
    }

    public static function fromGlobals(): self
    {
        // The variable is set by the Android runtime and Termux
        return self::fromHost(\PHP_OS_FAMILY, \getenv('ANDROID_ROOT') !== false);
    }

    /**
     * @param string $osFamily OS family in terms of {@see PHP_OS_FAMILY}.
     * @param bool $androidRuntime Whether the Android runtime is present.
     */
    public static function fromHost(string $osFamily, bool $androidRuntime): self
    {
        $os = self::tryFromString($osFamily) ?? throw new \OutOfRangeException(
            \sprintf(self::ERROR_UNKNOWN_OS, $osFamily),
        );

        // PHP reports Android as Linux
        return $os === self::Linux && $androidRuntime ? self::Android : $os;
    }

    public static function tryFromString(string $name): ?self
    {
        return match (\strtolower($name)) {
            'windows', 'win32', 'win64' => self::Windows,
            'bsd', 'freebsd' => self::BSD,
            'darwin', 'macos' => self::Darwin,
            'android' => self::Android,
            // The libc is a separate trait, see {@see Libc}
            'linux', 'alpine', 'unknown-musl' => self::Linux,
            default => null,
        };
    }

    public static function tryFromBuildName(string $name): ?self
    {
        // Android builds are also named after Linux, like `aarch64-linux-android`
        if (\preg_match('/(?:\b|_)android(?:\b|_)/i', $name) === 1) {
            return self::Android;
        }

        if (\preg_match(
            '/(?:\b|_)(windows|linux|darwin|macos|alpine|bsd|freebsd|win32|win64)(?:\b|_)/i',
            $name,
            $matches,
        ) === 1) {
            return self::tryFromString(\strtolower($matches[1]));
        }

        // Only Linux builds name the libc alone, like `unknown-musl`
        return Libc::tryFromBuildName($name) === Libc::Musl ? self::Linux : null;
    }

    public function getBinaryExtension(): string
    {
        return match ($this) {
            self::Windows => '.exe',
            default => '',
        };
    }
}
