<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\Archive\Internal;

use Internal\DLoad\Module\Archive\Archive as ArchiveInterface;

/**
 * Base abstract archive implementation
 *
 * Provides common functionality for archive handling.
 *
 * ```php
 * // Child class implementation example
 * class CustomArchive extends Archive
 * {
 *     public function extract(): \Generator
 *     {
 *         // Implementation for custom archive extraction
 *         foreach ($files as $file) {
 *             $fileTo = yield $file->getPathname() => $file;
 *             // Extract file if requested
 *         }
 *     }
 * }
 * ```
 *
 * @internal
 */
abstract class Archive implements ArchiveInterface
{
    /**
     * Creates archive handler and validates the archive file
     *
     * @param \SplFileInfo $archive Archive file
     * @throws \InvalidArgumentException When archive is invalid
     */
    public function __construct(\SplFileInfo $archive)
    {
        $this->assertArchiveValid($archive);
    }

    /**
     * Validates that archive file exists and is readable
     *
     * @param \SplFileInfo $archive Archive file to validate
     * @throws \InvalidArgumentException When archive is invalid
     */
    private function assertArchiveValid(\SplFileInfo $archive): void
    {
        if (! $archive->isFile()) {
            throw new \InvalidArgumentException(
                \sprintf('Archive "%s" is not a file.', $archive->getFilename()),
            );
        }

        if (! $archive->isReadable()) {
            throw new \InvalidArgumentException(
                \sprintf('Archive file "%s" is not readable.', $archive->getFilename()),
            );
        }
    }
}
