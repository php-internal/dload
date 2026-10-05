<?php

declare(strict_types=1);

namespace Internal\DLoad\Tests\Unit\Module\Downloader\Internal\AssetSelection\Rule;

use Internal\DLoad\Module\Common\OperatingSystem;
use Internal\DLoad\Module\Downloader\Internal\AssetSelection\Rule\OperatingSystemRule;
use Internal\DLoad\Tests\Unit\Module\Downloader\Internal\AssetSelection\Stub\RuleRunner;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Test;

#[Covers(OperatingSystemRule::class)]
final class OperatingSystemRuleTest
{
    private const ASSETS = [
        'tool-linux-amd64.zip',
        'tool-linux-amd64-android.zip',
        'tool-darwin-amd64.zip',
        'tool-amd64.zip',
    ];

    #[Test]
    public function aStrictSelectionKeepsOnlyTheHostOs(): void
    {
        $selection = RuleRunner::run(new OperatingSystemRule(OperatingSystem::Linux), self::ASSETS);

        Assert::same(RuleRunner::ranks($selection, 'os'), ['tool-linux-amd64.zip' => 0]);
    }

    #[Test]
    public function aGradualSelectionRanksOtherOsesLast(): void
    {
        $selection = RuleRunner::run(new OperatingSystemRule(OperatingSystem::Linux), self::ASSETS, strict: false);

        Assert::same(RuleRunner::ranks($selection, 'os'), [
            'tool-linux-amd64.zip' => 0,
            'tool-linux-amd64-android.zip' => 2,
            'tool-darwin-amd64.zip' => 2,
            'tool-amd64.zip' => 2,
        ]);
    }

    #[Test]
    public function androidKeepsLinuxBuildsAsAFallback(): void
    {
        $selection = RuleRunner::run(new OperatingSystemRule(OperatingSystem::Android), self::ASSETS);

        Assert::same(RuleRunner::ranks($selection, 'os'), [
            'tool-linux-amd64.zip' => 1,
            'tool-linux-amd64-android.zip' => 0,
        ]);
    }
}
