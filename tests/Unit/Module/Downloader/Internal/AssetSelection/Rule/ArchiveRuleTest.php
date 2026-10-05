<?php

declare(strict_types=1);

namespace Internal\DLoad\Tests\Unit\Module\Downloader\Internal\AssetSelection\Rule;

use Internal\DLoad\Module\Archive\ArchiveFactory;
use Internal\DLoad\Module\Downloader\Internal\AssetSelection\Rule\ArchiveRule;
use Internal\DLoad\Tests\Unit\Module\Downloader\Internal\AssetSelection\Stub\RuleRunner;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Test;

#[Covers(ArchiveRule::class)]
final class ArchiveRuleTest
{
    #[Test]
    public function supportedArchivesRankFirst(): void
    {
        $selection = RuleRunner::run(new ArchiveRule(new ArchiveFactory()), [
            'tool-linux-amd64',
            'tool-linux-amd64.deb',
            'tool-linux-amd64.tar.gz',
            'tool-linux-amd64.zip',
        ]);

        Assert::same(RuleRunner::ranks($selection, 'archive'), [
            'tool-linux-amd64' => 1,
            'tool-linux-amd64.deb' => 1,
            'tool-linux-amd64.tar.gz' => 0,
            'tool-linux-amd64.zip' => 0,
        ]);
    }
}
