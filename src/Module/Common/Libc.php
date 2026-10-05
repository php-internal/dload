<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\Common;

use Internal\Container\Factoriable;
use Internal\DLoad\Module\Common\Input\Build;

/**
 * C standard library a binary is linked against.
 *
 * Only Linux distributions differ here: Alpine and a few others use musl. Every other host is
 * treated as {@see self::Gnu}, which keeps musl builds a fallback there.
 *
 * ```php
 * // Recommended: Get from container (autowired with build config)
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

    public static function create(Build $config): self
    {
        return match (\strtolower((string) $config->os)) {
            'alpine', 'unknown-musl' => self::Musl,
            default => self::fromGlobals(),
        };
    }

    public static function fromGlobals(): self
    {
        // The musl dynamic loader exists on musl systems only, and checking it runs nothing
        return \PHP_OS_FAMILY === 'Linux' && \glob('/lib/ld-musl-*.so.1') !== []
            ? self::Musl
            : self::Gnu;
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
