<?php

declare(strict_types=1);

namespace Internal\DLoad\Tests\Unit\Module\Downloader\Internal\AssetSelection\Rule;

use Internal\DLoad\Module\Downloader\Internal\AssetSelection\Rule\MicroarchitectureLevelRule;
use Internal\DLoad\Tests\Unit\Module\Downloader\Internal\AssetSelection\Stub\RuleRunner;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Test;

#[Covers(MicroarchitectureLevelRule::class)]
final class MicroarchitectureLevelRuleTest
{
    #[Test]
    public function eachLevelAboveV1RanksTheAssetLower(): void
    {
        $selection = RuleRunner::run(new MicroarchitectureLevelRule(), [
            'tool_linux_amd64_v3.tar.gz',
            'tool_linux_amd64_v2.tar.gz',
            'tool_linux_amd64_v1.tar.gz',
            'tool_linux_amd64.tar.gz',
        ]);

        Assert::same(RuleRunner::ranks($selection, 'level'), [
            'tool_linux_amd64_v3.tar.gz' => 2,
            'tool_linux_amd64_v2.tar.gz' => 1,
            'tool_linux_amd64_v1.tar.gz' => 0,
            'tool_linux_amd64.tar.gz' => 0,
        ]);
    }

    #[Test]
    public function aStrictSelectionKeepsAHigherLevel(): void
    {
        $selection = RuleRunner::run(new MicroarchitectureLevelRule(), ['tool_linux_amd64_v3.tar.gz']);

        Assert::same(RuleRunner::ranks($selection, 'level'), ['tool_linux_amd64_v3.tar.gz' => 2]);
    }
}
