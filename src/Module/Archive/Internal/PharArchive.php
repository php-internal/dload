<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\Archive\Internal;

/**
 * Archive handler for standard PHAR archives
 *
 * ```php
 * // Creating and using a PHAR archive handler
 * $archive = new PharArchive(new \SplFileInfo('package.phar'));
 * foreach ($archive->extract() as $path => $fileInfo) {
 *     if (str_ends_with($path, '.php')) {
 *         yield new \SplFileInfo('/tmp/extract/' . basename($path));
 *     }
 * }
 * ```
 *
 * @internal
 */
final class PharArchive extends PharAwareArchive
{
    /**
     * Opens PHAR archive for reading
     *
     * @param \SplFileInfo $file Archive file
     */
    protected function open(\SplFileInfo $file): \PharData
    {
        return new \PharData($file->getPathname());
    }
}
