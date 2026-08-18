<?php

declare(strict_types=1);

namespace Internal\DLoad\Tests\Unit\Module\Archive\Internal;

use Internal\DLoad\Module\Archive\Internal\Archive;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Expect;
use Testo\Test;

#[Covers(Archive::class)]
final class ArchiveTest
{
    #[Test]
    public function constructorThrowsExceptionWhenFileDoesNotExist(): void
    {
        $file = \Mockery::mock(\SplFileInfo::class);
        $file->allows('isFile')->andReturn(false);
        $file->allows('getFilename')->andReturn('non-existent.zip');

        Expect::exception(\InvalidArgumentException::class)->withMessage('Archive "non-existent.zip" is not a file.');

        $this->createArchiveInstance($file);
    }

    #[Test]
    public function constructorThrowsExceptionWhenFileIsNotReadable(): void
    {
        $file = \Mockery::mock(\SplFileInfo::class);
        $file->allows('isFile')->andReturn(true);
        $file->allows('isReadable')->andReturn(false);
        $file->allows('getFilename')->andReturn('unreadable.zip');

        Expect::exception(\InvalidArgumentException::class)->withMessage('Archive file "unreadable.zip" is not readable.');

        $this->createArchiveInstance($file);
    }

    #[Test]
    public function constructorSucceedsWithValidFile(): void
    {
        $file = \Mockery::mock(\SplFileInfo::class);
        $file->allows('isFile')->andReturn(true);
        $file->allows('isReadable')->andReturn(true);

        $archive = $this->createArchiveInstance($file);

        Assert::instanceOf($archive, Archive::class);
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

            public function entries(): array
            {
                return ['test'];
            }
        };
    }
}
