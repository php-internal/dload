<?php

declare(strict_types=1);

namespace Internal\DLoad\Tests\Unit\Module\Downloader\Internal\AssetSelection\Rule;

use Internal\DLoad\Module\Common\Libc;
use Internal\DLoad\Module\Downloader\Internal\AssetSelection\Rule\LibcRule;
use Internal\DLoad\Tests\Unit\Module\Downloader\Internal\AssetSelection\Stub\RuleRunner;
use Internal\DLoad\Tests\Unit\Module\Downloader\Internal\AssetSelection\Stub\LibcContainer;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Test;

#[Covers(LibcRule::class)]
final class LibcRuleTest
{
    private const ASSETS = [
        'tool-x86_64-unknown-linux-gnu.tar.gz',
        'tool-x86_64-unknown-linux-musl.tar.gz',
        'tool-linux-amd64.tar.gz',
    ];

    #[Test]
    public function aGlibcHostRanksMuslBuildsLast(): void
    {
        $selection = RuleRunner::run(new LibcRule(new LibcContainer(Libc::Gnu)), self::ASSETS);

        Assert::same(RuleRunner::ranks($selection, 'libc'), [
            'tool-x86_64-unknown-linux-gnu.tar.gz' => 0,
            'tool-x86_64-unknown-linux-musl.tar.gz' => 1,
            'tool-linux-amd64.tar.gz' => 0,
        ]);
    }

    #[Test]
    public function aMuslHostRanksMuslBuildsFirst(): void
    {
        $selection = RuleRunner::run(new LibcRule(new LibcContainer(Libc::Musl)), self::ASSETS);

        Assert::same(RuleRunner::ranks($selection, 'libc'), [
            'tool-x86_64-unknown-linux-gnu.tar.gz' => 1,
            'tool-x86_64-unknown-linux-musl.tar.gz' => 0,
            'tool-linux-amd64.tar.gz' => 1,
        ]);
    }

    #[Test]
    public function androidRanksMuslBuildsFirst(): void
    {
        $selection = RuleRunner::run(new LibcRule(new LibcContainer(Libc::Bionic)), self::ASSETS);

        Assert::same(RuleRunner::ranks($selection, 'libc'), [
            'tool-x86_64-unknown-linux-gnu.tar.gz' => 1,
            'tool-x86_64-unknown-linux-musl.tar.gz' => 0,
            'tool-linux-amd64.tar.gz' => 1,
        ]);
    }

    #[Test]
    public function aSystemLibcRanksNothing(): void
    {
        $selection = RuleRunner::run(new LibcRule(new LibcContainer(Libc::System)), [
            'tool-x86_64-pc-windows-gnu.tar.gz',
            'tool-x86_64-pc-windows-msvc.zip',
        ]);

        Assert::same(RuleRunner::ranks($selection, 'libc'), [
            'tool-x86_64-pc-windows-gnu.tar.gz' => 0,
            'tool-x86_64-pc-windows-msvc.zip' => 0,
        ]);
    }

    #[Test]
    public function nothingIsRemoved(): void
    {
        $selection = RuleRunner::run(new LibcRule(new LibcContainer(Libc::Gnu)), self::ASSETS);

        Assert::same(RuleRunner::names($selection), self::ASSETS);
    }

    #[Test]
    public function theHostIsNotProbedWithoutAChoiceOfLibc(): void
    {
        $container = new LibcContainer(Libc::Gnu);

        $selection = RuleRunner::run(new LibcRule($container), ['tool-linux-amd64.tar.gz', 'tool-x86_64-unknown-linux-gnu.tar.gz']);

        Assert::same($container->requests, 0);
        Assert::same(RuleRunner::ranks($selection, 'libc'), [
            'tool-linux-amd64.tar.gz' => 0,
            'tool-x86_64-unknown-linux-gnu.tar.gz' => 0,
        ]);
    }

    #[Test]
    public function theHostIsProbedWhenTheLibcDecides(): void
    {
        $container = new LibcContainer(Libc::Musl);

        $selection = RuleRunner::run(new LibcRule($container), self::ASSETS);

        Assert::same($container->requests, 1);
        Assert::same(RuleRunner::ranks($selection, 'libc')['tool-x86_64-unknown-linux-musl.tar.gz'], 0);
    }
}
