<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\Archive\Internal;

use Internal\DLoad\Module\Archive\Exception\ArchiveException;

/**
 * Handler for non-archived files treated as single-file archives
 *
 * Provides a fallback Archive implementation for non-archived files,
 * treating them as if they were archives containing a single file.
 *
 * @internal
 */
final class NullArchive extends Archive
{
    /**
     * Creates a handler for a non-archived file
     *
     * @param \SplFileInfo $file Source file
     */
    public function __construct(
        private readonly \SplFileInfo $file,
    ) {
        parent::__construct($file);
    }

    /**
     * "Extracts" the file by yielding it as-is
     *
     * Treats the file as if it were the only item in an archive.
     * The key of the yielded value is the file's path.
     *
     * @return \Generator<non-empty-string, \SplFileInfo, \SplFileInfo|null, void>
     * @throws ArchiveException
     */
    public function extract(): \Generator
    {
        $this->file->isReadable() or throw new ArchiveException(
            \sprintf('Could not open "%s" for reading.', $this->file->getPathname()),
        );

        /** @var \SplFileInfo|null $fileTo */
        $fileTo = yield $this->file->getPathname() => $this->file;

        if ($fileTo instanceof \SplFileInfo) {
            $sourcePath = $this->file->getRealPath() ?: $this->file->getPathname();
            $destPath = $fileTo->getRealPath() ?: $fileTo->getPathname();

            \copy(
                $sourcePath,
                $destPath,
            );
        }
    }
}
