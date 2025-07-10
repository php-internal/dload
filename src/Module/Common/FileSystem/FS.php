<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\Common\FileSystem;

/**
 * File system utility class.
 *
 * @psalm-internal Internal\DLoad
 */
final class FS
{
    /**
     * Creates a directory.
     *
     * @param non-empty-string $path Path to the directory to create
     * @param int $mode Directory permissions (default: 0777)
     * @param bool $recursive Whether to create parent directories if they do not exist (default: true)
     *
     * @throws \RuntimeException If the directory could not be created
     */
    public static function mkdir(string $path, int $mode = 0777, bool $recursive = true): void
    {
        \is_dir($path) or \mkdir($path, $mode, $recursive) or \is_dir($path) or throw new \RuntimeException(
            \sprintf('Directory "%s" was not created.', $path),
        );
    }

    /**
     * Creates a temporary directory.
     *
     * @param non-empty-string|null $path Path to the temporary directory. If null, uses system temp directory.
     * @param non-empty-string|null $sub Optional subdirectory name to create within the temp directory.
     *
     * @return Path The created temporary directory path.
     */
    public static function tmpDir(?string $path = null, ?string $sub = null): Path
    {
        $result = Path::create($path ?? \sys_get_temp_dir());
        $sub === null or $result = $result->join($sub);
        $result->exists() or self::mkdir((string) $result);

        return $result;
    }
}
