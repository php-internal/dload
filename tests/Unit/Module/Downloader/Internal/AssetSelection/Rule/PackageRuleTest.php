<?php

declare(strict_types=1);

namespace Internal\DLoad\Tests\Unit\Module\Downloader\Internal\AssetSelection\Rule;

use Internal\DLoad\Module\Config\Schema\Action\Type;
use Internal\DLoad\Module\Downloader\Internal\AssetSelection\Rule\PackageRule;
use Internal\DLoad\Tests\Unit\Module\Downloader\Internal\AssetSelection\Stub\RuleRunner;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Test;

#[Covers(PackageRule::class)]
final class PackageRuleTest
{
    private const ASSETS = [
        'tool-linux-amd64',
        'tool-linux-amd64.tar.gz',
        'tool-linux-amd64.deb',
        'tool-linux-amd64.RPM',
        'tool-linux-amd64.apk',
        'tool-darwin-arm64.dmg',
        'tool-darwin-arm64.pkg',
        'tool-windows-amd64.msi',
        'tool-windows-amd64.exe',
        'tool-linux-amd64.AppImage',
    ];
    private const WITHOUT_PACKAGES = [
        'tool-linux-amd64',
        'tool-linux-amd64.tar.gz',
        'tool-windows-amd64.exe',
        'tool-linux-amd64.AppImage',
    ];

    #[Test]
    public function packagesAreRemovedWhenABinaryIsExpected(): void
    {
        $selection = RuleRunner::run(new PackageRule(), self::ASSETS);

        Assert::same(RuleRunner::names($selection), self::WITHOUT_PACKAGES);
    }

    #[Test]
    public function aBinaryActionRemovesPackagesToo(): void
    {
        $selection = RuleRunner::run(new PackageRule(), self::ASSETS, type: Type::Binary);

        Assert::same(RuleRunner::names($selection), self::WITHOUT_PACKAGES);
    }

    #[Test]
    public function packagesAreKeptWhenNoBinaryIsExpected(): void
    {
        $selection = RuleRunner::run(new PackageRule(), self::ASSETS, strict: false);

        Assert::same(RuleRunner::names($selection), self::ASSETS);
    }
}
