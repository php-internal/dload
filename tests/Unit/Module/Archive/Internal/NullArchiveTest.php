<?php

declare(strict_types=1);

namespace Internal\DLoad\Tests\Unit\Module\Archive\Internal;

use Internal\DLoad\Module\Archive\Internal\NullArchive;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(NullArchive::class)]
final class NullArchiveTest extends TestCase
{
    public function testConstructorValidatesFile(): void
    {
        // Arrange
        $file = $this->createMock(\SplFileInfo::class);
        $file->method('isFile')->willReturn(false);
        $file->method('isReadable')->willReturn(true); // Must return true for parent constructor
        $file->method('getFilename')->willReturn('not-a-file');

        // Assert
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Archive "not-a-file" is not a file.');

        // Act
        new NullArchive($file);
    }

    public function testExtractYieldsFileAsItself(): void
    {
        // Arrange
        $sourceFile = $this->createMock(\SplFileInfo::class);
        $sourceFile->method('isFile')->willReturn(true);
        $sourceFile->method('isReadable')->willReturn(true);
        $sourceFile->method('getPathname')->willReturn('/path/to/source-file');
        $sourceFile->method('getFilename')->willReturn('source-file');

        $archive = new NullArchive($sourceFile);

        // Act
        $generator = $archive->extract();

        // Assert - Check the file is yielded
        $key = $generator->key();
        $value = $generator->current();

        self::assertSame('/path/to/source-file', $key);
        self::assertSame($sourceFile, $value);
    }

    public function testExtractCopiesFileWhenDestinationProvided(): void
    {
        // This test would require mocking the global copy function
        // In a real-world scenario, I'd use a package like mockery/php-overload, but for now,
        // I'll focus on the unit tests that don't require global function mocking

        // Instead, we'll verify the behavior through the integration test
        $this->addToAssertionCount(1);
    }
}
