<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\Archive\Internal;

/**
 * Archive handler for TAR.GZ archives
 *
 * ```php
 * // Extracting from TAR.GZ archive
 * $archive = new TarPharArchive(new \SplFileInfo('package.tar.gz'));
 * foreach ($archive->extract() as $path => $fileInfo) {
 *     // Extract all files to a directory
 *     yield new \SplFileInfo('/tmp/extract/' . basename($path));
 * }
 * ```
 *
 * @internal
 */
final class TarPharArchive extends PharAwareArchive
{
    /**
     * Opens TAR.GZ archive for reading
     *
     * @param \SplFileInfo $file Archive file
     * @return \PharData
     */
    protected function open(\SplFileInfo $file): \PharData
    {
        return new \PharData($file->getPathname());
    }
}
