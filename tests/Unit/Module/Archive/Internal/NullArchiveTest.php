<?php

declare(strict_types=1);

namespace Internal\DLoad\Tests\Unit\Module\Archive\Internal;

use Internal\DLoad\Module\Archive\Internal\NullArchive;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Core\Exception\SkipTest;
use Testo\Expect;
use Testo\Test;

#[Covers(NullArchive::class)]
final class NullArchiveTest
{
    #[Test]
    public function constructorValidatesFile(): void
    {
        $file = \Mockery::mock(\SplFileInfo::class);
        $file->allows('isFile')->andReturn(false);
        $file->allows('isReadable')->andReturn(true); // Must return true for parent constructor
        $file->allows('getFilename')->andReturn('not-a-file');

        Expect::exception(\InvalidArgumentException::class)->withMessage('Archive "not-a-file" is not a file.');

        new NullArchive($file);
    }

    #[Test]
    public function extractYieldsFileAsItself(): void
    {
        $sourceFile = \Mockery::mock(\SplFileInfo::class);
        $sourceFile->allows('isFile')->andReturn(true);
        $sourceFile->allows('isReadable')->andReturn(true);
        $sourceFile->allows('getPathname')->andReturn('/path/to/source-file');
        $sourceFile->allows('getFilename')->andReturn('source-file');

        $archive = new NullArchive($sourceFile);

        $generator = $archive->extract();

        $key = $generator->key();
        $value = $generator->current();

        Assert::same($key, '/path/to/source-file');
        Assert::same($value, $sourceFile);
    }

    #[Test]
    public function extractCopiesFileWhenDestinationProvided(): never
    {
        // Copying goes through the global `copy()` function, which cannot be stubbed here.
        throw new SkipTest('Covered by ArchiveIntegrationTest.');
    }
}
