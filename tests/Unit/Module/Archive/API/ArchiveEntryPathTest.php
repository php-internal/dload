<?php

declare(strict_types=1);

namespace Internal\DLoad\Tests\Unit\Module\Archive\API;

use Internal\DLoad\Module\Archive\ArchiveEntryPath;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Data\DataProvider;
use Testo\Test;

#[Covers(ArchiveEntryPath::class)]
final class ArchiveEntryPathTest
{
    public static function provideTopLevelDirectories(): \Generator
    {
        yield 'single wrapping directory' => [
            ['pkg-1.0/bin/app', 'pkg-1.0/lib/app/lib.so', 'pkg-1.0/share/x.txt'],
            'pkg-1.0/',
        ];
        yield 'no wrapping directory when entries live at the root' => [
            ['bin/app', 'lib/app/lib.so'],
            '',
        ];
        yield 'no wrapping directory when top levels differ' => [
            ['pkg-1.0/bin/app', 'other/lib.so'],
            '',
        ];
        yield 'root-level file prevents stripping' => [
            ['pkg-1.0/bin/app', 'README.md'],
            '',
        ];
        yield 'empty archive' => [
            [],
            '',
        ];
        yield 'single wrapped file' => [
            ['pkg-1.0/bin/app'],
            'pkg-1.0/',
        ];
    }

    public static function provideRelativePaths(): \Generator
    {
        yield 'strips the common prefix' => ['pkg-1.0/bin/app', 'pkg-1.0/', 'bin/app'];
        yield 'keeps path when prefix is empty' => ['bin/app', '', 'bin/app'];
        yield 'keeps path when prefix does not match' => ['other/app', 'pkg-1.0/', 'other/app'];
        yield 'entry equal to the prefix is dropped' => ['pkg-1.0/', 'pkg-1.0/', null];
        yield 'parent traversal is rejected' => ['pkg-1.0/../../etc/passwd', 'pkg-1.0/', null];
        yield 'embedded traversal is rejected' => ['bin/../../etc', '', null];
    }

    #[DataProvider('provideTopLevelDirectories')]
    #[Test]
    public function commonTopLevelDirectoryDetectsWrappingDir(array $entries, string $expected): void
    {
        Assert::same(ArchiveEntryPath::commonTopLevelDirectory($entries), $expected);
    }

    #[DataProvider('provideRelativePaths')]
    #[Test]
    public function relativeStripsPrefixAndGuardsTraversal(
        string $entryPath,
        string $stripPrefix,
        ?string $expected,
    ): void {
        Assert::same(ArchiveEntryPath::relative($entryPath, $stripPrefix), $expected);
    }
}
