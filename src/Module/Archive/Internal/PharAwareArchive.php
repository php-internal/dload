<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\Archive\Internal;

use Internal\DLoad\Module\Archive\Exception\ArchiveException;

/**
 * Base class for PharData-based archive handlers
 *
 * Provides common functionality for archives handled by PHP's PharData.
 *
 * ```php
 * // Example implementation of a custom PharData-based archive
 * class CustomPharArchive extends PharAwareArchive
 * {
 *     protected function open(\SplFileInfo $file): \PharData
 *     {
 *         // Custom opening logic
 *         return new \PharData($file->getPathname(), $customFlags);
 *     }
 * }
 *
 * // Usage
 * $archive = new CustomPharArchive(new \SplFileInfo('archive.custom'));
 * foreach ($archive->extract() as $path => $fileInfo) {
 *     // Extract to destination
 *     yield new \SplFileInfo('/path/to/extract/' . basename($path));
 * }
 * ```
 *
 * @internal
 */
abstract class PharAwareArchive extends Archive
{
    /** @var \PharData Opened archive instance */
    protected \PharData $archive;

    /**
     * Creates and opens archive
     *
     * @param \SplFileInfo $asset Archive file
     * @throws \LogicException When archive cannot be opened
     */
    public function __construct(\SplFileInfo $asset)
    {
        parent::__construct($asset);
    }

    public function extract(): \Generator
    {
        $archive = $this->open($this->asset);
        $archive->isReadable() or throw new ArchiveException(
            \sprintf('Could not open "%s" for reading.', $archive->getPathname()),
        );

        /** @var \PharFileInfo $file */
        foreach (new \RecursiveIteratorIterator($archive) as $file) {
            /** @var \SplFileInfo|null $fileTo */
            $fileTo = yield $file->getPathname() => $file;
            $fileTo instanceof \SplFileInfo and \copy(
                $file->getPathname(),
                $fileTo->getRealPath() ?: $fileTo->getPathname(),
            );
        }
    }

    /**
     * Opens archive with specific format
     *
     * @param \SplFileInfo $file Archive file
     */
    abstract protected function open(\SplFileInfo $file): \PharData;
}
