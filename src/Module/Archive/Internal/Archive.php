<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\Archive\Internal;

use Internal\DLoad\Module\Archive\Archive as ArchiveInterface;

abstract class Archive implements ArchiveInterface
{
    /**
     * @param \SplFileInfo $archive
     */
    public function __construct(\SplFileInfo $archive)
    {
        $this->assertArchiveValid($archive);
    }

    /**
     * @param \SplFileInfo $archive
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
