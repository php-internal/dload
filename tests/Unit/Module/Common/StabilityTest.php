<?php

declare(strict_types=1);

namespace Internal\DLoad\Tests\Unit\Module\Common;

use Internal\DLoad\Module\Common\Input\Build;
use Internal\DLoad\Module\Common\Stability;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Stability::class)]
final class StabilityTest extends TestCase
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

    public function testAllCasesHaveUniqueValues(): void
    {
        // Arrange
        $cases = Stability::cases();
        $values = \array_map(static fn(Stability $case): string => $case->value, $cases);

        // Act
        $uniqueValues = \array_unique($values);

        // Assert
        self::assertCount(\count($values), $uniqueValues, 'All stability cases should have unique values');
    }

    #[DataProvider('provideStabilityCasesWithExpectedWeights')]
    public function testGetWeightReturnsExpectedValue(Stability $stability, int $expectedWeight): void
    {
        // Act
        $weight = $stability->getWeight();

        // Assert
        self::assertSame($expectedWeight, $weight);
    }

    public function testWeightsAreInDescendingOrder(): void
    {
        // Arrange
        $cases = Stability::cases();
        $weights = \array_map(static fn(Stability $case): int => $case->getWeight(), $cases);

        // Act
        $sortedWeights = $weights;
        \rsort($sortedWeights);

        // Assert
        self::assertSame($sortedWeights, $weights, 'Stability weights should be in descending order in the enum definition');
    }

    #[DataProvider('provideStabilityMeetsMinimumScenarios')]
    public function testMeetsMinimumComparesStabilityLevelsCorrectly(
        Stability $current,
        Stability $minimum,
        bool $expectedResult,
    ): void {
        // Act
        $result = $current->meetsMinimum($minimum);

        // Assert
        self::assertSame($expectedResult, $result);
    }

    public function testFromGlobalsReturnsStable(): void
    {
        // Act
        $stability = Stability::fromGlobals();

        // Assert
        self::assertSame(Stability::Stable, $stability);
    }

    #[DataProvider('provideValidStabilityStrings')]
    public function testFromStringReturnsCorrectStabilityForValidStrings(string $input, Stability $expected): void
    {
        // Act
        $result = Stability::fromString($input);

        // Assert
        self::assertSame($expected, $result);
    }

    #[DataProvider('provideInvalidStabilityStrings')]
    public function testFromStringReturnsNullForInvalidStrings(string $input): void
    {
        // Act
        $result = Stability::fromString($input);

        // Assert
        self::assertNull($result);
    }

    #[DataProvider('provideBuildConfigurationsForCreate')]
    public function testCreateReturnsCorrectStabilityFromBuildConfig(
        ?string $buildStability,
        Stability $expectedStability,
    ): void {
        // Arrange
        $build = new Build();
        $build->stability = $buildStability;

        // Act
        $result = Stability::create($build);

        // Assert
        self::assertSame($expectedStability, $result);
    }

    public function testCreateWithNullBuildStabilityUsesFromGlobals(): void
    {
        // Arrange
        $build = new Build();
        $build->stability = null;

        // Act
        $result = Stability::create($build);

        // Assert
        self::assertSame(Stability::fromGlobals(), $result);
    }

    public function testCreateWithInvalidBuildStabilityUsesFromGlobals(): void
    {
        // Arrange
        $build = new Build();
        $build->stability = 'completely-invalid-stability';

        // Act
        $result = Stability::create($build);

        // Assert
        self::assertSame(Stability::fromGlobals(), $result);
    }

    public function testEnumImplementsFactoriableInterface(): void
    {
        // Assert
        self::assertContains('Internal\DLoad\Service\Factoriable', \class_implements(Stability::class));
    }

    public function testAllEnumValuesAreStrings(): void
    {
        // Arrange
        $cases = Stability::cases();

        // Act & Assert
        foreach ($cases as $case) {
            self::assertIsString($case->value, "Stability case {$case->name} should have a string value");
        }
    }

    #[DataProvider('provideStabilityTransitivityScenarios')]
    public function testMeetsMinimumTransitivity(
        Stability $first,
        Stability $second,
        Stability $third,
        bool $expectedResult,
    ): void {
        // Arrange
        $firstMeetsSecond = $first->meetsMinimum($second);
        $secondMeetsThird = $second->meetsMinimum($third);

        // Act
        $firstMeetsThird = $first->meetsMinimum($third);

        // Assert
        if ($firstMeetsSecond && $secondMeetsThird) {
            self::assertSame(
                $expectedResult,
                $firstMeetsThird,
                'If A meets B and B meets C, then A should meet C (transitivity)',
            );
        } else {
            // If the premise is false, we can't test transitivity
            self::assertTrue(true, 'Transitivity test skipped due to false premise');
        }
    }

    public function testStabilityOrderingIsConsistent(): void
    {
        // Arrange
        $cases = Stability::cases();

        // Act & Assert
        for ($i = 0; $i < \count($cases) - 1; $i++) {
            for ($j = $i + 1; $j < \count($cases); $j++) {
                $higher = $cases[$i];
                $lower = $cases[$j];

                self::assertTrue(
                    $higher->meetsMinimum($lower),
                    "Stability {$higher->name} should meet minimum {$lower->name} based on enum order",
                );

                self::assertFalse(
                    $lower->meetsMinimum($higher),
                    "Stability {$lower->name} should not meet minimum {$higher->name} based on enum order",
                );
            }
        }
    }
}
