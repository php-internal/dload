<?php

declare(strict_types=1);

namespace Internal\DLoad\Tests\Unit\Module\Downloader\Internal\AssetSelection\Rule;

use Internal\DLoad\Module\Downloader\Internal\AssetSelection\Candidate;
use Internal\DLoad\Module\Downloader\Internal\AssetSelection\Rule\PackageRule;
use Internal\DLoad\Tests\Unit\Module\Downloader\Internal\AssetSelection\Stub\RuleRunner;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Test;

#[Covers(PackageRule::class)]
final class PackageRuleTest
{
    private const PACKAGES = [
        'tool-linux-amd64.deb',
        'tool-linux-amd64.RPM',
        'tool-linux-amd64.apk',
        'tool-linux-amd64.snap',
        'tool-linux-amd64.flatpak',
        'tool-darwin-arm64.dmg',
        'tool-darwin-arm64.pkg',
        'tool-windows-amd64.msi',
        'tool-windows-amd64.msix',
        'tool-windows-amd64.msixbundle',
        'tool-windows-amd64.appx',
        'tool-windows-amd64.appxbundle',
        'tool.1.0.0.nupkg',
    ];
    private const OTHERS = [
        'tool-linux-amd64',
        'tool-linux-amd64.tar.gz',
        'tool-windows-amd64.exe',
        'tool-linux-amd64.AppImage',
    ];

    #[Test]
    public function packagesAreRemovedWhenABinaryIsExpected(): void
    {
        $selection = RuleRunner::run(new PackageRule(), [...self::OTHERS, ...self::PACKAGES]);

        Assert::same(RuleRunner::names($selection), self::OTHERS);
    }

    #[Test]
    public function removedPackagesAreKeptForTheReport(): void
    {
        $selection = RuleRunner::run(new PackageRule(), [...self::OTHERS, ...self::PACKAGES]);

        Assert::same(\array_keys($selection->removed), [PackageRule::KEY]);
        Assert::same(
            \array_map(static fn(Candidate $candidate): string => $candidate->asset->getName(), $selection->removed[PackageRule::KEY]),
            self::PACKAGES,
        );
    }

    #[Test]
    public function packagesAreKeptWhenNoBinaryIsExpected(): void
    {
        $selection = RuleRunner::run(new PackageRule(), [...self::OTHERS, ...self::PACKAGES], strict: false);

        Assert::same(RuleRunner::names($selection), [...self::OTHERS, ...self::PACKAGES]);
        Assert::same($selection->removed, []);
    }
}
