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

    public static function provideMacArchitectures(): iterable
    {
        yield 'Apple Silicon' => [Architecture::ARM_64, [
            'tool-darwin-universal.zip' => ArchitectureRule::UNIVERSAL,
            'tool-darwin-x86_64.zip' => ArchitectureRule::EMULATED,
            'tool-darwin-aarch64.zip' => 0,
        ]];
        yield 'Intel' => [Architecture::X86_64, [
            'tool-darwin-universal.zip' => ArchitectureRule::UNIVERSAL,
            'tool-darwin-x86_64.zip' => 0,
            'tool-darwin-aarch64.zip' => 3,
        ]];
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
            'tool-x86_64.zip' => 3,
            'tool-aarch64.zip' => 0,
            'tool.zip' => 3,
        ]);
    }

    #[DataProvider('provideEmulatingHosts')]
    #[Test]
    public function armHostsThatEmulateX86KeepItsBuildsAsAFallback(OperatingSystem $os): void
    {
        $selection = RuleRunner::run(new ArchitectureRule(Architecture::ARM_64, $os), self::ASSETS);

        Assert::same(RuleRunner::ranks($selection, 'arch'), [
            'tool-x86_64.zip' => ArchitectureRule::EMULATED,
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

    /**
     * @param array<non-empty-string, int> $expected
     */
    #[DataProvider('provideMacArchitectures')]
    #[Test]
    public function aUniversalBuildRanksRightAfterTheHostBuildOnMacOs(Architecture $arch, array $expected): void
    {
        $selection = RuleRunner::run(new ArchitectureRule($arch, OperatingSystem::Darwin), [
            'tool-darwin-universal.zip',
            'tool-darwin-x86_64.zip',
            'tool-darwin-aarch64.zip',
        ], strict: false);

        Assert::same(RuleRunner::ranks($selection, 'arch'), $expected);
    }

    #[Test]
    public function aUniversalMacOsBuildHasNoArchitectureElsewhere(): void
    {
        $rule = new ArchitectureRule(Architecture::X86_64, OperatingSystem::Linux);
        $assets = ['tool-darwin-universal.zip', 'tool-linux-x86_64.zip'];

        Assert::same(RuleRunner::ranks(RuleRunner::run($rule, $assets), 'arch'), ['tool-linux-x86_64.zip' => 0]);
        Assert::same(RuleRunner::ranks(RuleRunner::run($rule, $assets, strict: false), 'arch'), [
            'tool-darwin-universal.zip' => 3,
            'tool-linux-x86_64.zip' => 0,
        ]);
    }
}
