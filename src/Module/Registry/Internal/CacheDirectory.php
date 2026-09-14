<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\Registry\Internal;

use Internal\Path;

/**
 * Resolves the default directory of the version registry.
 *
 * Follows the platform conventions for per-user cache data, so the database survives between
 * projects and runs without any configuration:
 *
 * - `$XDG_CACHE_HOME/dload` when the variable is set;
 * - `%LOCALAPPDATA%\dload\cache` on Windows;
 * - `$HOME/.cache/dload` otherwise;
 * - a per-user directory under the system temporary directory as the last resort.
 *
 * @internal
 * @psalm-internal Internal\DLoad
 */
final class CacheDirectory
{
    /**
     * @param array<string, mixed> $env Environment variables.
     * @param bool $windows Whether the platform conventions of Windows apply.
     */
    public static function resolve(array $env, bool $windows = \DIRECTORY_SEPARATOR === '\\'): Path
    {
        $xdg = self::variable($env, 'XDG_CACHE_HOME');
        if ($xdg !== null) {
            return Path::create($xdg)->join('dload');
        }

        $localAppData = self::variable($env, 'LOCALAPPDATA');
        if ($localAppData !== null && $windows) {
            return Path::create($localAppData)->join('dload', 'cache');
        }

        $home = self::variable($env, 'HOME') ?? self::variable($env, 'USERPROFILE');
        if ($home !== null) {
            return Path::create($home)->join('.cache', 'dload');
        }

        // The temporary directory is shared by every user of the host: keep the registries apart
        $user = self::variable($env, 'USER') ?? self::variable($env, 'USERNAME') ?? self::processOwner();

        return Path::create(\sys_get_temp_dir())->join('dload-cache-' . (string) \preg_replace('/[^A-Za-z0-9._-]+/', '_', $user));
    }

    /**
     * @return non-empty-string
     */
    private static function processOwner(): string
    {
        if (\function_exists('posix_geteuid')) {
            return (string) \posix_geteuid();
        }

        $owner = \get_current_user();

        return $owner === '' ? 'default' : $owner;
    }

    /**
     * @param array<string, mixed> $env
     * @return non-empty-string|null
     */
    private static function variable(array $env, string $name): ?string
    {
        /** @var mixed $value */
        $value = $env[$name] ?? null;
        $path = \is_string($value) ? \rtrim(\trim($value), '/\\') : '';

        return $path === '' ? null : $path;
    }
}
