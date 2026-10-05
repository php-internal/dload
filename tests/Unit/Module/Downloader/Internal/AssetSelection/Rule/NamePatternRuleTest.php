<?php

declare(strict_types=1);

namespace Internal\DLoad\Tests\Unit\Module\Downloader\Internal\AssetSelection\Rule;

use Internal\DLoad\Module\Downloader\Internal\AssetSelection\Rule\NamePatternRule;
use Internal\DLoad\Tests\Unit\Module\Downloader\Internal\AssetSelection\Stub\RuleRunner;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Test;

#[Covers(NamePatternRule::class)]
final class NamePatternRuleTest
{
    #[Test]
    public function assetsNotMatchingThePatternAreRemoved(): void
    {
        $selection = RuleRunner::run(
            new NamePatternRule(),
            ['tool-linux-amd64.zip', 'protoc-gen-tool-linux-amd64.zip', 'tool-darwin-arm64.zip'],
            pattern: '/^tool-/',
        );

        Assert::same(RuleRunner::names($selection), ['tool-linux-amd64.zip', 'tool-darwin-arm64.zip']);
    }

    #[Test]
    public function anInvalidPatternMatchesNothing(): void
    {
        $selection = RuleRunner::run(new NamePatternRule(), ['tool-linux-amd64.zip'], pattern: '/tool[/');

        Assert::true($selection->isEmpty());
    }

    #[Test]
    public function nothingIsRanked(): void
    {
        $selection = RuleRunner::run(new NamePatternRule(), ['tool-linux-amd64.zip']);

        Assert::same($selection->candidates[0]->ranks, []);
    }
}
