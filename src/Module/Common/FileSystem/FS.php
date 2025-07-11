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
     * @param non-empty-string|Path $path Path to the directory to create
     * @param int $mode Directory permissions (default: 0777)
     * @param bool $recursive Whether to create parent directories if they do not exist (default: true)
     *
     * @throws \RuntimeException If the directory could not be created
     */
    public static function mkdir(string|Path $path, int $mode = 0777, bool $recursive = true): void
    {
        $path = (string) $path;
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

    /**
     * Removes a file or directory.
     *
     * @param Path $path Path to the file or directory to remove
     *
     * @throws \RuntimeException If the file or directory could not be removed
     */
    public static function remove(Path $path): bool
    {
        return !$path->exists() or match (true) {
            $path->isFile() => self::removeFile($path),
            $path->isDir() => self::removeDir($path),
            default => throw new \RuntimeException("Path `{$path->absolute()}` is neither a file nor a directory."),
        };
    }

    /**
     * Removes a file.
     *
     * @param Path $path Path to the file to remove
     *
     * @throws \RuntimeException If the file could not be removed
     */
    public static function removeFile(Path $path): bool
    {
        return \unlink($path->__toString());
    }

    /**
     * Removes a directory and all its contents.
     *
     * @param Path $path Path to the directory to remove
     * @param bool $recursive Whether to remove the directory recursively (default: true)
     *
     * @throws \RuntimeException If the directory could not be removed
     */
    public static function removeDir(Path $path, bool $recursive = true): bool
    {
        if (!$path->exists()) {
            return true;
        }

        if ($recursive) {
            /** @var \DirectoryIterator $item */
            foreach (new \DirectoryIterator($path->__toString()) as $item) {
                $item->isDot() or self::remove(Path::create($item->getPathname()));
            }
        }

        return \rmdir($path->__toString());
    }

    /**
     * Moves a file from one path to another.
     *
     * @param Path $from Source file path
     * @param Path $to Destination file path
     * @param bool $overwrite Whether to overwrite the destination file if it exists (default: false)
     *
     * @throws \RuntimeException If the move operation fails
     */
    public static function moveFile(Path $from, Path $to, bool $overwrite = false): bool
    {
        if ($from->absolute() === $to->absolute()) {
            return true; // No need to move if paths are the same
        }

        if ($to->exists()) {
            !$overwrite and throw new \RuntimeException(
                "Failed to move file from `{$from}` to `{$to}`: target file already exists.",
            );

            self::remove($to);
        }

        return \rename($from->__toString(), $to->__toString());
    }
}
