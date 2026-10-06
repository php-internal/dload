<?php

declare(strict_types=1);

namespace Internal\DLoad\Tests\Unit\Module\Downloader\Internal\AssetSelection\Rule;

use Internal\DLoad\Module\Downloader\Internal\AssetSelection\Rule\CompanionRule;
use Internal\DLoad\Tests\Unit\Module\Downloader\Internal\AssetSelection\Stub\RuleRunner;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Test;

#[Covers(CompanionRule::class)]
final class CompanionRuleTest
{
    #[Test]
    public function checksumsSignaturesAndSbomsAreRemoved(): void
    {
        $selection = RuleRunner::run(new CompanionRule(), [
            'tool-linux-amd64.tar.gz',
            'tool-linux-amd64.tar.gz.sha256',
            'tool-linux-amd64.tar.gz.sha256sum',
            'tool-linux-amd64.tar.gz.asc',
            'tool-linux-amd64.tar.gz.sig',
            'tool-linux-amd64.sbom.json',
            'tool_1.0.0_checksums.txt',
            'SHA256SUMS',
            'tool.phar',
        ]);

        Assert::same(RuleRunner::names($selection), ['tool-linux-amd64.tar.gz', 'tool.phar']);
    }

    #[Test]
    public function otherTextFilesStay(): void
    {
        $selection = RuleRunner::run(new CompanionRule(), ['notes.txt', 'schema.json']);

        Assert::same(RuleRunner::names($selection), ['notes.txt', 'schema.json']);
    }
}
