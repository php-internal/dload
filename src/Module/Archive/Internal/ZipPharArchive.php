<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\Archive\Internal;

/**
 * Archive handler for ZIP archives
 *
 * ```php
 * // Extracting specific file types from ZIP archive
 * $archive = new ZipPharArchive(new \SplFileInfo('package.zip'));
 * $extractDir = '/tmp/extract';
 *
 * foreach ($archive->extract() as $path => $fileInfo) {
 *     // Only extract binary files
 *     if (pathinfo($path, PATHINFO_EXTENSION) === 'bin') {
 *         yield new \SplFileInfo($extractDir . '/' . basename($path));
 *     }
 * }
 * ```
 *
 * @internal
 */
final class ZipPharArchive extends PharAwareArchive
{
    /**
     * Opens ZIP archive for reading
     *
     * Uses ZIP and GZ formats to properly handle ZIP archives.
     *
     * @param \SplFileInfo $file Archive file
     * @return \PharData
     */
    protected function open(\SplFileInfo $file): \PharData
    {
        $format = \Phar::ZIP | \Phar::GZ;

        return new \PharData($file->getPathname(), 0, null, $format);
    }
}
