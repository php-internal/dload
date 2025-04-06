<?php

declare(strict_types=1);

namespace Internal\DLoad\Tests\Unit\Module\Archive\Internal;

use Internal\DLoad\Module\Archive\Exception\ArchiveException;
use Internal\DLoad\Module\Archive\Internal\PharAwareArchive;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(PharAwareArchive::class)]
final class PharAwareArchiveTest extends TestCase
{
    private \SplFileInfo $fileInfo;

    public function testExtractThrowsExceptionWhenArchiveIsNotReadable(): void
    {
        // Arrange
        $pharData = $this->createMock(\PharData::class);
        $pharData->method('isReadable')->willReturn(false);
        $pharData->method('getPathname')->willReturn('unreadable.phar');

        $archive = $this->createPharAwareArchive($pharData);

        // Assert
        $this->expectException(ArchiveException::class);
        $this->expectExceptionMessage('Could not open "unreadable.phar" for reading.');

        // Act
        \iterator_to_array($archive->extract());
    }

    protected function setUp(): void
    {
        // Arrange
        $this->fileInfo = $this->createMock(\SplFileInfo::class);
        $this->fileInfo->method('isFile')->willReturn(true);
        $this->fileInfo->method('isReadable')->willReturn(true);
    }

    /**
     * Creates a concrete implementation of the abstract PharAwareArchive for testing
     */
    private function createPharAwareArchive(\PharData $pharData): PharAwareArchive
    {
        return new class($this->fileInfo, $pharData) extends PharAwareArchive {
            private \PharData $testPharData;

            public function __construct(\SplFileInfo $archive, \PharData $pharData)
            {
                $this->testPharData = $pharData;
                parent::__construct($archive);
            }

            protected function open(\SplFileInfo $file): \PharData
            {
                return $this->testPharData;
            }
        };
    }
}
