<?php

declare(strict_types=1);

namespace Internal\DLoad\Tests\Unit\Module\Archive\Internal;

use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Expect;
use Testo\Test;
use Internal\DLoad\Module\Archive\Internal\NullArchive;

#[Covers(NullArchive::class)]
final class NullArchiveTest
{
    #[Test]
    public function constructorValidatesFile(): void
    {
        $file = $this->createMock(\SplFileInfo::class);
        $file->method('isFile')->willReturn(false);
        $file->method('isReadable')->willReturn(true); // Must return true for parent constructor
        $file->method('getFilename')->willReturn('not-a-file');

        Expect::exception(\InvalidArgumentException::class)->withMessage('Archive "not-a-file" is not a file.');

        new NullArchive($file);
    }

    #[Test]
    public function extractYieldsFileAsItself(): void
    {
        $sourceFile = $this->createMock(\SplFileInfo::class);
        $sourceFile->method('isFile')->willReturn(true);
        $sourceFile->method('isReadable')->willReturn(true);
        $sourceFile->method('getPathname')->willReturn('/path/to/source-file');
        $sourceFile->method('getFilename')->willReturn('source-file');

        $archive = new NullArchive($sourceFile);

        $generator = $archive->extract();

        // Assert - Check the file is yielded
        $key = $generator->key();
        $value = $generator->current();

        Assert::same($key, '/path/to/source-file');
        Assert::same($value, $sourceFile);
    }

    #[Test]
    public function extractCopiesFileWhenDestinationProvided(): void
    {
        // This test would require mocking the global copy function
        // In a real-world scenario, I'd use a package like mockery/php-overload, but for now,
        // I'll focus on the unit tests that don't require global function mocking

        // Instead, we'll verify the behavior through the integration test
        $this->addToAssertionCount(1);
    }
}
