<?php

declare(strict_types=1);

namespace Internal\DLoad\Tests\Unit\Module\Downloader\Internal\AssetSelection\Rule;

use Internal\DLoad\Module\Archive\ArchiveFactory;
use Internal\DLoad\Module\Config\Schema\Action\Type;
use Internal\DLoad\Module\Downloader\Internal\AssetSelection\Candidate;
use Internal\DLoad\Module\Downloader\Internal\AssetSelection\Rule\UnsupportedArchiveRule;
use Internal\DLoad\Tests\Unit\Module\Downloader\Internal\AssetSelection\Stub\RuleRunner;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Test;

#[Covers(UnsupportedArchiveRule::class)]
final class UnsupportedArchiveRuleTest
{
    private const UNSUPPORTED = [
        'tool-linux-amd64.tar.xz',
        'tool-linux-amd64.TXZ',
        'tool-linux-amd64.tar.bz2',
        'tool-linux-amd64.tbz2',
        'tool-linux-amd64.tar.zst',
        'tool-1.0.0-1-x86_64.pkg.tar.zst',
        'tool-linux-amd64.tar',
        'tool-linux-amd64.tgz',
        'tool-linux-amd64.xz',
        'tool-linux-amd64.bz2',
        'tool-linux-amd64.lz4',
        'tool-windows-amd64.7z',
        'tool-windows-amd64.rar',
    ];
    private const OTHERS = [
        'tool-linux-amd64',
        'tool-linux-amd64.AppImage',
        'tool-windows-amd64.exe',
        'tool-linux-amd64.tar.gz',
        'tool-linux-amd64.gz',
        'tool-windows-amd64.ZIP',
        'tool.phar',
        'tool-linux-amd64.deb',
        'tool-linux-amd64.run',
    ];

    #[Test]
    public function archivesDloadCannotUnpackAreRemovedWhenABinaryIsExpected(): void
    {
        $selection = RuleRunner::run(self::rule(), [...self::OTHERS, ...self::UNSUPPORTED]);

        Assert::same(RuleRunner::names($selection), self::OTHERS);
        Assert::same(\array_keys($selection->removed), [UnsupportedArchiveRule::KEY]);
        Assert::same(
            \array_map(
                static fn(Candidate $candidate): string => $candidate->asset->getName(),
                $selection->removed[UnsupportedArchiveRule::KEY],
            ),
            self::UNSUPPORTED,
        );
    }

    #[Test]
    public function archivesAreKeptWhenNoBinaryIsExpected(): void
    {
        $selection = RuleRunner::run(self::rule(), [...self::OTHERS, ...self::UNSUPPORTED], strict: false);

        Assert::same(RuleRunner::names($selection), [...self::OTHERS, ...self::UNSUPPORTED]);
        Assert::same($selection->removed, []);
    }

    #[Test]
    public function anArchiveActionWithABinaryLosesThemToo(): void
    {
        $selection = RuleRunner::run(self::rule(), ['tool-linux-amd64.tar.gz', 'tool-linux-amd64.tar.xz'], type: Type::Archive);

        Assert::same(RuleRunner::names($selection), ['tool-linux-amd64.tar.gz']);
    }

    #[Test]
    public function aFormatGainingAnExtractorIsKept(): void
    {
        $factory = new ArchiveFactory();
        $factory->extend(static fn(): null => null, ['tar.xz']);

        $selection = RuleRunner::run(new UnsupportedArchiveRule($factory), ['tool-linux-amd64.tar.xz', 'tool-linux-amd64.7z']);

        Assert::same(RuleRunner::names($selection), ['tool-linux-amd64.tar.xz']);
    }

    private static function rule(): UnsupportedArchiveRule
    {
        return new UnsupportedArchiveRule(new ArchiveFactory());
    }
}
