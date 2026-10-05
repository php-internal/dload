<?php

declare(strict_types=1);

namespace Internal\DLoad\Tests\Unit\Module\Downloader\Internal\AssetSelection\Rule;

use Internal\DLoad\Module\Common\Architecture;
use Internal\DLoad\Module\Downloader\Internal\AssetSelection\Rule\ArchitectureRule;
use Internal\DLoad\Tests\Unit\Module\Downloader\Internal\AssetSelection\Stub\RuleRunner;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Test;

#[Covers(ArchitectureRule::class)]
final class ArchitectureRuleTest
{
    private const ASSETS = [
        'tool-linux-x86_64.zip',
        'tool-linux-aarch64.zip',
        'tool-linux.zip',
    ];

    #[Test]
    public function aStrictSelectionKeepsOnlyTheHostArchitecture(): void
    {
        $selection = RuleRunner::run(new ArchitectureRule(Architecture::ARM_64), self::ASSETS);

        Assert::same(RuleRunner::ranks($selection, 'arch'), ['tool-linux-aarch64.zip' => 0]);
    }

    #[Test]
    public function aGradualSelectionRanksOtherArchitecturesLast(): void
    {
        $selection = RuleRunner::run(new ArchitectureRule(Architecture::ARM_64), self::ASSETS, strict: false);

        Assert::same(RuleRunner::ranks($selection, 'arch'), [
            'tool-linux-x86_64.zip' => 1,
            'tool-linux-aarch64.zip' => 0,
            'tool-linux.zip' => 1,
        ]);
    }
}
