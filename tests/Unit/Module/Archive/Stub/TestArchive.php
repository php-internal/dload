<?php

declare(strict_types=1);

namespace Internal\DLoad\Tests\Unit\Module\Archive\Stub;

use Internal\DLoad\Module\Archive\Archive;
use Internal\DLoad\Module\Archive\Exception\ArchiveException;

/**
 * Test implementation of the Archive interface for unit testing
 */
class TestArchive implements Archive
{
    private array $files = [];
    private bool $throwsException = false;
    private string $exceptionMessage = 'Archive extraction failed';

    /**
     * @param \SplFileInfo $archiveFile The archive file
     */
    public function __construct(
        public readonly \SplFileInfo $archiveFile,
    ) {}

    /**
     * Configure the stub to throw an exception during extraction
     */
    public function throwExceptionOnExtract(?string $message = null): self
    {
        $this->throwsException = true;
        if ($message !== null) {
            $this->exceptionMessage = $message;
        }
        return $this;
    }

    /**
     * Add a test file to be returned during extraction
     */
    public function addFile(string $path, \SplFileInfo $fileInfo): self
    {
        $this->files[$path] = $fileInfo;
        return $this;
    }

    public function extract(): \Generator
    {
        if ($this->throwsException) {
            throw new ArchiveException($this->exceptionMessage);
        }

        foreach ($this->files as $path => $fileInfo) {
            $fileTo = yield $path => $fileInfo;

            // Simulate file extraction if destination is specified
            if ($fileTo instanceof \SplFileInfo) {
                // In a real implementation, this would copy the file
                // For testing, we just record the extraction occurred
                yield $path => $fileTo;
            }
        }
    }
}
