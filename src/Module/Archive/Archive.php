<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\Archive;

use Internal\DLoad\Module\Archive\Exception\ArchiveException;

/**
 * Archive extraction interface
 *
 * Provides methods to extract contents from archive files.
 */
interface Archive
{
    /**
     * Iterate through archive files and extract them
     *
     * Iterates through all files in the archive and yields {@see \SplFileInfo} objects.
     * The generator key is the path of the entry relative to the archive root, using
     * forward slashes as separators (e.g. `bin/rapira`). This makes it possible to
     * preserve the internal directory structure on extraction.
     * If a {@see \SplFileInfo} is yielded back into the generator, the file will be
     * extracted to the given location.
     *
     * ```php
     * // Extract only specific files, preserving their relative paths
     * $archive = $factory->create(new \SplFileInfo('archive.zip'));
     * foreach ($archive->extract() as $path => $fileInfo) {
     *     if (str_ends_with($path, '.php')) {
     *         // Extract PHP files keeping the archive layout
     *         yield new \SplFileInfo('/path/to/extract/' . $path);
     *     }
     * }
     * ```
     *
     * @return \Generator<non-empty-string, \SplFileInfo, \SplFileInfo|null, void>
     *         Key is the entry path relative to the archive root (forward slashes).
     * @throws ArchiveException
     */
    public function extract(): \Generator;
}
