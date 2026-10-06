<?php

declare(strict_types=1);

namespace Internal\DLoad\Tests\Unit\Module\Downloader\Internal\AssetSelection\Rule;

use Internal\DLoad\Module\Common\Architecture;
use Internal\DLoad\Module\Common\OperatingSystem;
use Internal\DLoad\Module\Downloader\Internal\AssetSelection\Rule\ArchitectureRule;
use Internal\DLoad\Tests\Unit\Module\Downloader\Internal\AssetSelection\Stub\RuleRunner;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Data\DataProvider;
use Testo\Test;

#[Covers(ArchitectureRule::class)]
final class ArchitectureRuleTest
{
    private const ASSETS = [
        'tool-x86_64.zip',
        'tool-aarch64.zip',
        'tool.zip',
    ];

    public static function provideEmulatingHosts(): iterable
    {
        yield 'macOS' => [OperatingSystem::Darwin];
        yield 'Windows' => [OperatingSystem::Windows];
    }

    #[Test]
    public function aStrictSelectionKeepsOnlyTheHostArchitecture(): void
    {
        $selection = RuleRunner::run(new ArchitectureRule(Architecture::ARM_64, OperatingSystem::Linux), self::ASSETS);

        Assert::same(RuleRunner::ranks($selection, 'arch'), ['tool-aarch64.zip' => 0]);
    }

    #[Test]
    public function aGradualSelectionRanksOtherArchitecturesLast(): void
    {
        $selection = RuleRunner::run(
            new ArchitectureRule(Architecture::ARM_64, OperatingSystem::Linux),
            self::ASSETS,
            strict: false,
        );

        Assert::same(RuleRunner::ranks($selection, 'arch'), [
            'tool-x86_64.zip' => 2,
            'tool-aarch64.zip' => 0,
            'tool.zip' => 2,
        ]);
    }

    #[DataProvider('provideEmulatingHosts')]
    #[Test]
    public function armHostsThatEmulateX86KeepItsBuildsAsAFallback(OperatingSystem $os): void
    {
        $selection = RuleRunner::run(new ArchitectureRule(Architecture::ARM_64, $os), self::ASSETS);

        Assert::same(RuleRunner::ranks($selection, 'arch'), [
            'tool-x86_64.zip' => 1,
            'tool-aarch64.zip' => 0,
        ]);
    }

    #[DataProvider('provideEmulatingHosts')]
    #[Test]
    public function x86HostsDoNotRunArmBuilds(OperatingSystem $os): void
    {
        $selection = RuleRunner::run(new ArchitectureRule(Architecture::X86_64, $os), self::ASSETS);

        Assert::same(RuleRunner::ranks($selection, 'arch'), ['tool-x86_64.zip' => 0]);
    }
}
