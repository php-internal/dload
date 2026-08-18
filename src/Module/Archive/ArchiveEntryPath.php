<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\Archive;

/**
 * Pure helpers for reasoning about archive entry paths during structure-preserving extraction.
 *
 * @internal
 */
final class ArchiveEntryPath
{
    /**
     * Detects a single top-level directory shared by every archive entry.
     *
     * Mirrors `tar --strip-components=1`: when the whole archive is wrapped in one directory
     * (e.g. `package-1.0/...`), that prefix is returned so it can be stripped on extraction.
     *
     * @param list<non-empty-string> $entries Entry paths relative to the archive root (forward slashes)
     * @return string The prefix to strip including the trailing slash (e.g. `package-1.0/`),
     *         or an empty string when there is no single wrapping directory
     */
    public static function commonTopLevelDirectory(array $entries): string
    {
        $prefix = null;
        foreach ($entries as $entry) {
            $slash = \strpos($entry, '/');
            // A root-level entry means there is no single wrapping directory
            if ($slash === false || $slash === 0) {
                return '';
            }

            $top = \substr($entry, 0, $slash);
            $prefix ??= $top;
            if ($top !== $prefix) {
                return '';
            }
        }

        return $prefix === null ? '' : $prefix . '/';
    }

    /**
     * Computes the relative destination path for an archive entry, stripping the common wrapping
     * directory and guarding against path traversal (zip-slip).
     *
     * @param non-empty-string $entryPath Entry path relative to the archive root (forward slashes)
     * @param string $stripPrefix Leading directory to remove (e.g. `package-1.0/`), or an empty string
     * @return non-empty-string|null Cleaned relative path, or null when the entry must be skipped
     *         (empty after stripping, or it would escape the destination)
     */
    public static function relative(string $entryPath, string $stripPrefix): ?string
    {
        $relative = $stripPrefix !== '' && \str_starts_with($entryPath, $stripPrefix)
            ? \substr($entryPath, \strlen($stripPrefix))
            : $entryPath;

        $relative = \trim($relative, '/');
        if ($relative === '') {
            return null;
        }

        // Zip-slip guard: never allow an entry to escape the destination directory
        foreach (\explode('/', $relative) as $segment) {
            if ($segment === '..') {
                return null;
            }
        }

        return $relative;
    }
}
