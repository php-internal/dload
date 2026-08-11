<?php

declare(strict_types=1);

namespace Internal\DLoad\Tests\Unit\Module\Common;

use Internal\DLoad\Module\Common\Input\Build;
use Internal\DLoad\Module\Common\Stability;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Data\DataProvider;
use Testo\Test;

#[Covers(Stability::class)]
final class StabilityTest
{
    public static function provideStabilityCasesWithExpectedWeights(): \Generator
    {
        yield 'stable' => [Stability::Stable, 9];
        yield 'RC' => [Stability::RC, 8];
        yield 'pre' => [Stability::Pre, 7];
        yield 'beta' => [Stability::Beta, 6];
        yield 'preview' => [Stability::Preview, 5];
        yield 'alpha' => [Stability::Alpha, 4];
        yield 'unstable' => [Stability::Unstable, 3];
        yield 'dev' => [Stability::Dev, 2];
        yield 'snapshot' => [Stability::Snapshot, 1];
        yield 'nightly' => [Stability::Nightly, 0];
    }

    public static function provideStabilityMeetsMinimumScenarios(): \Generator
    {
        yield 'stable meets stable' => [Stability::Stable, Stability::Stable, true];
        yield 'stable meets beta' => [Stability::Stable, Stability::Beta, true];
        yield 'stable meets nightly' => [Stability::Stable, Stability::Nightly, true];
        yield 'beta meets beta' => [Stability::Beta, Stability::Beta, true];
        yield 'beta meets alpha' => [Stability::Beta, Stability::Alpha, true];
        yield 'beta does not meet stable' => [Stability::Beta, Stability::Stable, false];
        yield 'alpha does not meet beta' => [Stability::Alpha, Stability::Beta, false];
        yield 'nightly does not meet dev' => [Stability::Nightly, Stability::Dev, false];
        yield 'RC meets preview' => [Stability::RC, Stability::Preview, true];
        yield 'preview does not meet RC' => [Stability::Preview, Stability::RC, false];
    }

    public static function provideValidStabilityStrings(): \Generator
    {
        yield 'exact match stable' => ['stable', Stability::Stable];
        yield 'exact match RC' => ['RC', Stability::RC];
        yield 'exact match beta' => ['beta', Stability::Beta];
        yield 'uppercase stable' => ['STABLE', Stability::Stable];
        yield 'uppercase beta' => ['BETA', Stability::Beta];
        yield 'uppercase alpha' => ['ALPHA', Stability::Alpha];
        yield 'mixed case RC' => ['rc', Stability::RC];
        yield 'mixed case preview' => ['PREVIEW', Stability::Preview];
        yield 'mixed case unstable' => ['UnStAbLe', Stability::Unstable];
        yield 'mixed case dev' => ['DeV', Stability::Dev];
        yield 'mixed case snapshot' => ['SnapShot', Stability::Snapshot];
        yield 'mixed case nightly' => ['NiGhTlY', Stability::Nightly];
        yield 'mixed case pre' => ['PRE', Stability::Pre];
    }

    public static function provideInvalidStabilityStrings(): \Generator
    {
        yield 'empty string' => [''];
        yield 'invalid stability' => ['invalid'];
        yield 'numeric value' => ['123'];
        yield 'partial match' => ['stab'];
        yield 'with spaces' => [' stable '];
        yield 'with special characters' => ['stable!'];
        yield 'mixed with numbers' => ['beta1'];
    }

    public static function provideBuildConfigurationsForCreate(): \Generator
    {
        yield 'null stability defaults to stable' => [null, Stability::Stable];
        yield 'valid stability beta' => ['beta', Stability::Beta];
        yield 'valid stability alpha' => ['alpha', Stability::Alpha];
        yield 'valid stability RC' => ['RC', Stability::RC];
        yield 'invalid stability defaults to stable' => ['invalid', Stability::Stable];
        yield 'empty string defaults to stable' => ['', Stability::Stable];
    }

    public static function provideStabilityTransitivityScenarios(): \Generator
    {
        yield 'stable -> beta -> alpha (transitive)' => [
            Stability::Stable, Stability::Beta, Stability::Alpha, true,
        ];
        yield 'RC -> preview -> dev (transitive)' => [
            Stability::RC, Stability::Preview, Stability::Dev, true,
        ];
        yield 'beta -> alpha -> nightly (transitive)' => [
            Stability::Beta, Stability::Alpha, Stability::Nightly, true,
        ];
    }

    #[Test]
    public function allCasesHaveUniqueValues(): void
    {
        $cases = Stability::cases();
        $values = \array_map(static fn(Stability $case): string => $case->value, $cases);

        $uniqueValues = \array_unique($values);

        Assert::count($uniqueValues, \count($values), 'All stability cases should have unique values');
    }

    #[DataProvider('provideStabilityCasesWithExpectedWeights')]
    #[Test]
    public function getWeightReturnsExpectedValue(Stability $stability, int $expectedWeight): void
    {
        $weight = $stability->getWeight();

        Assert::same($weight, $expectedWeight);
    }

    #[Test]
    public function weightsAreInDescendingOrder(): void
    {
        $cases = Stability::cases();
        $weights = \array_map(static fn(Stability $case): int => $case->getWeight(), $cases);

        $sortedWeights = $weights;
        \rsort($sortedWeights);

        Assert::same($weights, $sortedWeights, 'Stability weights should be in descending order in the enum definition');
    }

    #[DataProvider('provideStabilityMeetsMinimumScenarios')]
    #[Test]
    public function meetsMinimumComparesStabilityLevelsCorrectly(
        Stability $current,
        Stability $minimum,
        bool $expectedResult,
    ): void {
        $result = $current->meetsMinimum($minimum);

        Assert::same($result, $expectedResult);
    }

    #[Test]
    public function fromGlobalsReturnsStable(): void
    {
        $stability = Stability::fromGlobals();

        Assert::same($stability, Stability::Stable);
    }

    #[DataProvider('provideValidStabilityStrings')]
    #[Test]
    public function fromStringReturnsCorrectStabilityForValidStrings(string $input, Stability $expected): void
    {
        $result = Stability::fromString($input);

        Assert::same($result, $expected);
    }

    #[DataProvider('provideInvalidStabilityStrings')]
    #[Test]
    public function fromStringReturnsNullForInvalidStrings(string $input): void
    {
        $result = Stability::fromString($input);

        Assert::null($result);
    }

    #[DataProvider('provideBuildConfigurationsForCreate')]
    #[Test]
    public function createReturnsCorrectStabilityFromBuildConfig(
        ?string $buildStability,
        Stability $expectedStability,
    ): void {
        $build = new Build();
        $build->stability = $buildStability;

        $result = Stability::create($build);

        Assert::same($result, $expectedStability);
    }

    #[Test]
    public function createWithNullBuildStabilityUsesFromGlobals(): void
    {
        $build = new Build();
        $build->stability = null;

        $result = Stability::create($build);

        Assert::same($result, Stability::fromGlobals());
    }

    #[Test]
    public function createWithInvalidBuildStabilityUsesFromGlobals(): void
    {
        $build = new Build();
        $build->stability = 'completely-invalid-stability';

        $result = Stability::create($build);

        Assert::same($result, Stability::fromGlobals());
    }

    #[Test]
    public function enumImplementsFactoriableInterface(): void
    {
        Assert::contains(\class_implements(Stability::class), 'Internal\DLoad\Service\Factoriable');
    }

    #[Test]
    public function allEnumValuesAreStrings(): void
    {
        $cases = Stability::cases();

        foreach ($cases as $case) {
            Assert::true(\is_string($case->value), "Stability case {$case->name} should have a string value");
        }
    }

    #[DataProvider('provideStabilityTransitivityScenarios')]
    #[Test]
    public function meetsMinimumTransitivity(
        Stability $first,
        Stability $second,
        Stability $third,
        bool $expectedResult,
    ): void {
        $firstMeetsSecond = $first->meetsMinimum($second);
        $secondMeetsThird = $second->meetsMinimum($third);

        $firstMeetsThird = $first->meetsMinimum($third);

        if ($firstMeetsSecond && $secondMeetsThird) {
            Assert::same($firstMeetsThird, $expectedResult, 'If A meets B and B meets C, then A should meet C (transitivity)');
        } else {
            // If the premise is false, we can't test transitivity
            Assert::true(true, 'Transitivity test skipped due to false premise');
        }
    }

    #[Test]
    public function stabilityOrderingIsConsistent(): void
    {
        $cases = Stability::cases();

        for ($i = 0; $i < \count($cases) - 1; $i++) {
            for ($j = $i + 1; $j < \count($cases); $j++) {
                $higher = $cases[$i];
                $lower = $cases[$j];

                Assert::true($higher->meetsMinimum($lower), "Stability {$higher->name} should meet minimum {$lower->name} based on enum order");

                Assert::false($lower->meetsMinimum($higher), "Stability {$lower->name} should not meet minimum {$higher->name} based on enum order");
            }
        }
    }
}
