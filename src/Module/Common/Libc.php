<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\Common;

use Internal\Container\Factoriable;
use Internal\DLoad\Module\Common\Input\Build;

/**
 * C standard library of a host or a build.
 *
 * Builds name only {@see self::Gnu} or {@see self::Musl}; the other cases describe hosts.
 *
 * ```php
 * // Recommended: Get from container (autowired with build config); detection runs once
 * $libc = $container->get(Libc::class);
 *
 * // Or read it from a build name
 * $libc = Libc::tryFromBuildName('tool-x86_64-unknown-linux-musl.tar.gz');
 * ```
 *
 * @internal
 */
enum Libc: string implements Factoriable
{
    /**
     * glibc: most Linux distributions.
     */
    case Gnu = 'gnu';

    /**
     * musl: Alpine and a few other Linux distributions.
     */
    case Musl = 'musl';

    /**
     * Android's own libc: it runs no glibc build, while musl builds are usually static.
     */
    case Bionic = 'bionic';

    /**
     * The one libc the OS ships, like on Windows, macOS and BSD: builds do not differ by it.
     */
    case System = 'system';

    public static function create(Build $config, OperatingSystem $os): self
    {
        return match (true) {
            \in_array(\strtolower((string) $config->os), ['alpine', 'unknown-musl'], true) => self::Musl,
            $os === OperatingSystem::Android => self::Bionic,
            $os !== OperatingSystem::Linux => self::System,
            default => self::fromGlobals(),
        };
    }

    public static function fromGlobals(): self
    {
        return self::detect(\PHP_OS_FAMILY, '/');
    }

    /**
     * @param string $osFamily OS family in terms of {@see PHP_OS_FAMILY}.
     * @param non-empty-string $root Root of the file system to look for the musl loader in.
     */
    public static function detect(string $osFamily, string $root): self
    {
        if ($osFamily !== 'Linux') {
            return self::System;
        }

        // The musl dynamic loader exists on musl systems only, and checking it runs nothing
        $loaders = \glob(\rtrim($root, '/') . '/lib/ld-musl-*.so.1');

        // `glob()` reports an error as `false`, which must not count as a found loader
        return \is_array($loaders) && $loaders !== [] ? self::Musl : self::Gnu;
    }

    /**
     * Reads the libc a build is linked against from its name.
     *
     * @return self::Gnu|self::Musl|null Null when the name tells nothing.
     */
    public static function tryFromBuildName(string $name): ?self
    {
        if (\preg_match('/(?:\b|_)(musl(?:eabi(?:hf)?)?|alpine|gnu(?:eabi(?:hf)?)?|glibc)(?:\b|_)/i', $name, $matches) !== 1) {
            return null;
        }

        $token = \strtolower($matches[1]);
        return \str_starts_with($token, 'musl') || $token === 'alpine' ? self::Musl : self::Gnu;
    }

    /**
     * Whether a host with this libc prefers the build.
     *
     * A build that names no libc counts as a glibc one.
     *
     * @param self|null $build Libc the build names.
     */
    public function prefers(?self $build): bool
    {
        return match ($this) {
            self::Gnu => $build !== self::Musl,
            self::Musl, self::Bionic => $build === self::Musl,
            self::System => true,
        };
    }
}
