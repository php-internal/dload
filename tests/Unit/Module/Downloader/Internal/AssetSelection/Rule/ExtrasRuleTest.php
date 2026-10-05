<?php

declare(strict_types=1);

namespace Internal\DLoad\Tests\Unit\Module\Downloader\Internal\AssetSelection\Rule;

use Internal\DLoad\Module\Downloader\Internal\AssetSelection\Rule\ExtrasRule;
use Internal\DLoad\Tests\Unit\Module\Downloader\Internal\AssetSelection\Stub\RuleRunner;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Test;

#[Covers(ExtrasRule::class)]
final class ExtrasRuleTest
{
    #[Test]
    public function eachExtraTokenRanksTheAssetLower(): void
    {
        $selection = RuleRunner::run(new ExtrasRule(), [
            'bun-linux-x64-baseline-profile.zip',
            'bun-linux-x64-baseline.zip',
            'bun-linux-x64.zip',
        ]);

        Assert::same(RuleRunner::ranks($selection, 'extras'), [
            'bun-linux-x64-baseline-profile.zip' => 3,
            'bun-linux-x64-baseline.zip' => 2,
            'bun-linux-x64.zip' => 1,
        ]);
    }

    #[Test]
    public function aChecksumCountsAsItsAsset(): void
    {
        $selection = RuleRunner::run(new ExtrasRule(), ['tool-linux-amd64.tar.gz', 'tool-linux-amd64.tar.gz.sha256']);

        Assert::same(RuleRunner::ranks($selection, 'extras'), [
            'tool-linux-amd64.tar.gz' => 1,
            'tool-linux-amd64.tar.gz.sha256' => 1,
        ]);
    }
}
