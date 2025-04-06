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
     * If a {@see \SplFileInfo} is yielded back into the generator, the file will be
     * extracted to the given location.
     *
     * ```php
     * // Extract only specific files
     * $archive = $factory->create(new \SplFileInfo('archive.zip'));
     * foreach ($archive->extract() as $path => $fileInfo) {
     *     if (str_ends_with($path, '.php')) {
     *         // Extract PHP files to a specific directory
     *         yield new \SplFileInfo('/path/to/extract/' . basename($path));
     *     }
     * }
     * ```
     *
     * @return \Generator<non-empty-string, \SplFileInfo, \SplFileInfo|null, void>
     * @throws ArchiveException
     */
    public function extract(): \Generator;
}
