<?php

declare(strict_types=1);

namespace Internal\DLoad\Tests\Unit\Module\Downloader\Exception;

use Testo\Codecov\Covers;
use Testo\Test;
use Internal\DLoad\Module\Downloader\Exception\NothingExtracted;

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

        self::assertStringContainsString(
            'Nothing was extracted from `roadrunner-2025.1.15-windows-amd64.zip`: '
            . 'none of the 3 file(s) inside matches the extraction rules.',
            $message,
        );
        self::assertStringContainsString('Extraction rules: binary `/^roadrunner-.*/`', $message);
        self::assertStringContainsString('Files in the asset: CHANGELOG.md, LICENSE, rr.exe', $message);
    }

    #[Test]
    public function messageTruncatesLongFileList(): void
    {
        $files = \array_map(static fn(int $i): string => "file-{$i}.txt", \range(1, 25));

        $message = (new NothingExtracted('archive.tar.gz', [], $files))->getMessage();

        self::assertStringContainsString('Extraction rules: none', $message);
        self::assertStringContainsString('file-20.txt', $message);
        self::assertStringNotContainsString('file-21.txt', $message);
        self::assertStringContainsString('and 5 more', $message);
    }
}
