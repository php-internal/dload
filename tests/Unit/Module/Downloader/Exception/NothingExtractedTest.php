<?php

declare(strict_types=1);

namespace Internal\DLoad\Tests\Unit\Module\Downloader\Exception;

use Internal\DLoad\Module\Downloader\Exception\NothingExtracted;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Test;

#[Covers(NothingExtracted::class)]
final class NothingExtractedTest
{
    #[Test]
    public function messageListsRulesAndArchiveContent(): void
    {
        $exception = new NothingExtracted(
            assetName: 'roadrunner-2025.1.15-windows-amd64.zip',
            rules: ['binary `/^roadrunner-.*/`'],
            files: ['CHANGELOG.md', 'LICENSE', 'rr.exe'],
        );

        $message = $exception->getMessage();

        Assert::string($message)->contains(
            'Nothing was extracted from `roadrunner-2025.1.15-windows-amd64.zip`: '
            . 'none of the 3 file(s) inside matches the extraction rules.',
        );
        Assert::string($message)->contains('Extraction rules: binary `/^roadrunner-.*/`');
        Assert::string($message)->contains('Files in the asset: CHANGELOG.md, LICENSE, rr.exe');
    }

    #[Test]
    public function messageTruncatesLongFileList(): void
    {
        $files = \array_map(static fn(int $i): string => "file-{$i}.txt", \range(1, 25));

        $message = (new NothingExtracted('archive.tar.gz', [], $files))->getMessage();

        Assert::string($message)->contains('Extraction rules: none');
        Assert::string($message)->contains('file-20.txt');
        Assert::string($message)->notContains('file-21.txt');
        Assert::string($message)->contains('and 5 more');
    }
}
