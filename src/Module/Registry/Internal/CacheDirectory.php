<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\Registry\Internal;

/**
 * Resolves the default directory of the version registry.
 *
 * Follows the platform conventions for per-user cache data, so the database survives between
 * projects and runs without any configuration:
 *
 * - `$XDG_CACHE_HOME/dload` when the variable is set;
 * - `%LOCALAPPDATA%\dload\cache` on Windows;
 * - `$HOME/.cache/dload` otherwise;
 * - the system temporary directory as the last resort.
 *
 * @internal
 * @psalm-internal Internal\DLoad
 */
final class CacheDirectory
{
    /**
     * @param array<string, mixed> $env Environment variables.
     * @return non-empty-string
     */
    public static function resolve(array $env): string
    {
        $xdg = self::variable($env, 'XDG_CACHE_HOME');
        if ($xdg !== null) {
            return $xdg . \DIRECTORY_SEPARATOR . 'dload';
        }

        $localAppData = self::variable($env, 'LOCALAPPDATA');
        if ($localAppData !== null && \DIRECTORY_SEPARATOR === '\\') {
            return $localAppData . \DIRECTORY_SEPARATOR . 'dload' . \DIRECTORY_SEPARATOR . 'cache';
        }

        $home = self::variable($env, 'HOME') ?? self::variable($env, 'USERPROFILE');
        if ($home !== null) {
            return $home . \DIRECTORY_SEPARATOR . '.cache' . \DIRECTORY_SEPARATOR . 'dload';
        }

        return \sys_get_temp_dir() . \DIRECTORY_SEPARATOR . 'dload-cache';
    }

    /**
     * @param array<string, mixed> $env
     * @return non-empty-string|null
     */
    private static function variable(array $env, string $name): ?string
    {
        $value = $env[$name] ?? null;

        return \is_string($value) && \trim($value) !== '' ? \rtrim($value, '/\\') : null;
    }
}
