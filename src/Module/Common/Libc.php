<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\Common;

use Internal\Container\Factoriable;
use Internal\DLoad\Module\Common\Input\Build;

/**
 * C standard library a binary is linked against.
 *
 * Only Linux distributions differ here: Alpine and a few others use musl. Android counts as
 * {@see self::Musl}: its own libc runs no glibc build, while musl builds are usually static.
 * Every other host is treated as {@see self::Gnu}, which keeps musl builds a fallback there.
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
    case Gnu = 'gnu';
    case Musl = 'musl';

    public static function create(Build $config, OperatingSystem $os): self
    {
        return match (true) {
            \in_array(\strtolower((string) $config->os), ['alpine', 'unknown-musl'], true) => self::Musl,
            $os === OperatingSystem::Android => self::Musl,
            $os !== OperatingSystem::Linux => self::Gnu,
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
            return self::Gnu;
        }

        // The musl dynamic loader exists on musl systems only, and checking it runs nothing
        $loaders = \glob(\rtrim($root, '/') . '/lib/ld-musl-*.so.1');

        // `glob()` reports an error as `false`, which must not count as a found loader
        return \is_array($loaders) && $loaders !== [] ? self::Musl : self::Gnu;
    }

    public static function tryFromBuildName(string $name): ?self
    {
        if (\preg_match('/(?:\b|_)(musl(?:eabi(?:hf)?)?|alpine|gnu(?:eabi(?:hf)?)?|glibc)(?:\b|_)/i', $name, $matches) !== 1) {
            return null;
        }

        $token = \strtolower($matches[1]);
        return \str_starts_with($token, 'musl') || $token === 'alpine' ? self::Musl : self::Gnu;
    }
}
