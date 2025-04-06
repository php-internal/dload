<?php

declare(strict_types=1);

namespace Internal\DLoad\Tests\Unit\Module\Archive\API;

use Internal\DLoad\Module\Archive\Archive;
use Internal\DLoad\Module\Archive\Exception\ArchiveException;
use Internal\DLoad\Tests\Unit\Module\Archive\Stub\TestArchive;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Archive::class)]
final class ArchiveTest extends TestCase
{
    private \SplFileInfo $archiveFile;

    public static function provideFileTypes(): \Generator
    {
        $files = [
            'file1.txt' => new \SplFileInfo('/path/to/file1.txt'),
            'file2.php' => new \SplFileInfo('/path/to/file2.php'),
            'file3.php' => new \SplFileInfo('/path/to/file3.php'),
            'file4.json' => new \SplFileInfo('/path/to/file4.json'),
        ];

        yield 'txt files' => [$files, 'txt', 1];
        yield 'php files' => [$files, 'php', 2];
        yield 'json files' => [$files, 'json', 1];
        yield 'non-existent extension' => [$files, 'jpg', 0];
    }

    public function testExtractYieldsFilesFromArchive(): void
    {
        // Arrange
        $file1 = new \SplFileInfo('/path/to/file1.txt');
        $file2 = new \SplFileInfo('/path/to/file2.txt');

        $archive = new TestArchive($this->archiveFile);
        $archive->addFile('file1.txt', $file1);
        $archive->addFile('file2.txt', $file2);

        // Act
        $result = [];
        foreach ($archive->extract() as $path => $fileInfo) {
            $result[$path] = $fileInfo;
        }

        // Assert
        self::assertCount(2, $result);
        self::assertSame($file1, $result['file1.txt']);
        self::assertSame($file2, $result['file2.txt']);
    }

    public function testExtractThrowsArchiveException(): void
    {
        // Arrange
        $archive = new TestArchive($this->archiveFile);
        $archive->throwExceptionOnExtract('Custom error message');

        // Assert
        $this->expectException(ArchiveException::class);
        $this->expectExceptionMessage('Custom error message');

        // Act
        \iterator_to_array($archive->extract());
    }

    public function testExtractReturnsDestinationFileWhenRequested(): void
    {
        // Arrange
        $sourceFile = new \SplFileInfo('/path/to/source.txt');
        $destinationFile = new \SplFileInfo('/path/to/destination.txt');

        $archive = new TestArchive($this->archiveFile);
        $archive->addFile('source.txt', $sourceFile);

        // Act
        $generator = $archive->extract();
        $path = $generator->key();
        $info = $generator->current();
        $result = $generator->send($destinationFile);

        // Assert
        self::assertSame('source.txt', $path);
        self::assertSame($sourceFile, $info);
        self::assertSame($destinationFile, $result);
    }

    #[DataProvider('provideFileTypes')]
    public function testExtractFilteringByFileType(array $files, string $extension, int $expectedCount): void
    {
        // Arrange
        $archive = new TestArchive($this->archiveFile);

        foreach ($files as $path => $fileInfo) {
            $archive->addFile($path, $fileInfo);
        }

        // Act
        $extracted = [];
        foreach ($archive->extract() as $path => $fileInfo) {
            // Filter files by extension
            if (\pathinfo($path, PATHINFO_EXTENSION) === $extension) {
                $extracted[$path] = $fileInfo;
            }
        }

        // Assert
        self::assertCount($expectedCount, $extracted);
    }

    protected function setUp(): void
    {
        // Arrange
        $this->archiveFile = new \SplFileInfo(__FILE__); // Use this file as a valid file
    }
}
