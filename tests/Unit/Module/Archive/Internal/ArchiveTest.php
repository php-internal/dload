<?php

declare(strict_types=1);

namespace Internal\DLoad\Tests\Unit\Module\Archive\Internal;

use Internal\DLoad\Module\Archive\Internal\Archive;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[\Testo\Codecov\Covers(Archive::class)]
final class ArchiveTest
{
    #[\Testo\Test]
    public function testConstructorThrowsExceptionWhenFileDoesNotExist(): void
    {
        // Arrange
        $file = $this->createMock(\SplFileInfo::class);
        $file->method('isFile')->willReturn(false);
        $file->method('getFilename')->willReturn('non-existent.zip');

        // Assert
        \Testo\Expect::exception(\InvalidArgumentException::class)->withMessage('Archive "non-existent.zip" is not a file.');

        // Act
        $this->createArchiveInstance($file);
    }

    #[\Testo\Test]
    public function testConstructorThrowsExceptionWhenFileIsNotReadable(): void
    {
        // Arrange
        $file = $this->createMock(\SplFileInfo::class);
        $file->method('isFile')->willReturn(true);
        $file->method('isReadable')->willReturn(false);
        $file->method('getFilename')->willReturn('unreadable.zip');

        // Assert
        \Testo\Expect::exception(\InvalidArgumentException::class)->withMessage('Archive file "unreadable.zip" is not readable.');

        // Act
        $this->createArchiveInstance($file);
    }

    #[\Testo\Test]
    public function testConstructorSucceedsWithValidFile(): void
    {
        // Arrange
        $file = $this->createMock(\SplFileInfo::class);
        $file->method('isFile')->willReturn(true);
        $file->method('isReadable')->willReturn(true);

        // Act
        $archive = $this->createArchiveInstance($file);

        // Assert
        \Testo\Assert::instanceOf($archive, Archive::class);
    }

    /**
     * Creates a concrete implementation of the abstract Archive class for testing
     */
    private function createArchiveInstance(\SplFileInfo $file): Archive
    {
        return new class($file) extends Archive {
            public function extract(): \Generator
            {
                // Minimal implementation for testing the constructor
                yield 'test' => new \SplFileInfo('test');
            }
        };
    }
}
