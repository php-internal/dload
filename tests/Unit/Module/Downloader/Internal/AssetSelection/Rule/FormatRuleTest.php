<?php

declare(strict_types=1);

namespace Internal\DLoad\Tests\Unit\Module\Downloader\Internal\AssetSelection\Rule;

use Internal\DLoad\Module\Archive\ArchiveFactory;
use Internal\DLoad\Module\Config\Schema\Action\Type;
use Internal\DLoad\Module\Downloader\Internal\AssetSelection\Rule\FormatRule;
use Internal\DLoad\Tests\Unit\Module\Downloader\Internal\AssetSelection\Stub\RuleRunner;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Test;

#[Covers(FormatRule::class)]
final class FormatRuleTest
{
    private const ASSETS = [
        'tool-linux-amd64',
        'tool-linux-amd64.tar.gz',
        'tool-linux-amd64.zip',
        'tool-linux-amd64.deb',
        'tool.phar',
        'tool.phar.asc',
    ];

    #[Test]
    public function aPharActionKeepsOnlyPharFiles(): void
    {
        $selection = RuleRunner::run(self::rule(), self::ASSETS, type: Type::Phar);

        Assert::same(RuleRunner::names($selection), ['tool.phar']);
    }

    #[Test]
    public function anArchiveActionKeepsOnlySupportedArchives(): void
    {
        $selection = RuleRunner::run(self::rule(), self::ASSETS, type: Type::Archive);

        // A phar is an archive dload can extract too
        Assert::same(RuleRunner::names($selection), ['tool-linux-amd64.tar.gz', 'tool-linux-amd64.zip', 'tool.phar']);
    }

    #[Test]
    public function extensionsAreMatchedRegardlessOfCase(): void
    {
        $selection = RuleRunner::run(self::rule(), ['Tool.PHAR'], type: Type::Phar);

        Assert::same(RuleRunner::names($selection), ['Tool.PHAR']);
    }

    #[Test]
    public function otherActionsKeepEveryFormat(): void
    {
        Assert::same(RuleRunner::names(RuleRunner::run(self::rule(), self::ASSETS)), self::ASSETS);
        Assert::same(RuleRunner::names(RuleRunner::run(self::rule(), self::ASSETS, type: Type::Binary)), self::ASSETS);
    }

    private static function rule(): FormatRule
    {
        return new FormatRule(new ArchiveFactory());
    }
}
