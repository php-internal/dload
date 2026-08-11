<?php

declare(strict_types=1);

namespace Internal\DLoad\Tests\Unit\Module\Archive\API;

use Internal\DLoad\Module\Archive\Archive;
use Internal\DLoad\Module\Archive\Exception\ArchiveException;
use Internal\DLoad\Tests\Unit\Module\Archive\Stub\TestArchive;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Data\DataProvider;
use Testo\Expect;
use Testo\Lifecycle\BeforeTest;
use Testo\Test;

#[Covers(Archive::class)]
final class ArchiveTest
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

    #[Test]
    public function extractYieldsFilesFromArchive(): void
    {
        $file1 = new \SplFileInfo('/path/to/file1.txt');
        $file2 = new \SplFileInfo('/path/to/file2.txt');

        $archive = new TestArchive($this->archiveFile);
        $archive->addFile('file1.txt', $file1);
        $archive->addFile('file2.txt', $file2);

        $result = [];
        foreach ($archive->extract() as $path => $fileInfo) {
            $result[$path] = $fileInfo;
        }

        Assert::count($result, 2);
        Assert::same($result['file1.txt'], $file1);
        Assert::same($result['file2.txt'], $file2);
    }

    #[Test]
    public function extractThrowsArchiveException(): void
    {
        $archive = new TestArchive($this->archiveFile);
        $archive->throwExceptionOnExtract('Custom error message');

        Expect::exception(ArchiveException::class)->withMessage('Custom error message');

        \iterator_to_array($archive->extract());
    }

    #[Test]
    public function extractReturnsDestinationFileWhenRequested(): void
    {
        $sourceFile = new \SplFileInfo('/path/to/source.txt');
        $destinationFile = new \SplFileInfo('/path/to/destination.txt');

        $archive = new TestArchive($this->archiveFile);
        $archive->addFile('source.txt', $sourceFile);

        $generator = $archive->extract();
        $path = $generator->key();
        $info = $generator->current();
        $result = $generator->send($destinationFile);

        Assert::same($path, 'source.txt');
        Assert::same($info, $sourceFile);
        Assert::same($result, $destinationFile);
    }

    #[DataProvider('provideFileTypes')]
    #[Test]
    public function extractFilteringByFileType(array $files, string $extension, int $expectedCount): void
    {
        $archive = new TestArchive($this->archiveFile);

        foreach ($files as $path => $fileInfo) {
            $archive->addFile($path, $fileInfo);
        }

        $extracted = [];
        foreach ($archive->extract() as $path => $fileInfo) {
            // Filter files by extension
            if (\pathinfo($path, PATHINFO_EXTENSION) === $extension) {
                $extracted[$path] = $fileInfo;
            }
        }

        Assert::count($extracted, $expectedCount);
    }

    #[BeforeTest]
    protected function prepare(): void
    {
        $this->archiveFile = new \SplFileInfo(__FILE__); // Use this file as a valid file
    }
}
