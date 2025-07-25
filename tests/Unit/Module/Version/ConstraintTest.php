<?php

declare(strict_types=1);

namespace Internal\DLoad\Tests\Unit\Module\Version;

use Internal\DLoad\Module\Common\Stability;
use Internal\DLoad\Module\Version\Constraint;
use Internal\DLoad\Module\Version\Version;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Constraint::class)]
final class ConstraintTest extends TestCase
{
    public static function provideValidConstraints(): \Generator
    {
        // Basic version constraints
        yield 'simple version' => [
            '^2.12.0',
            '^2.12.0',
            null,
            Stability::Stable,
            'Simple version constraint without suffix or stability',
        ];

        yield 'tilde version' => [
            '~1.20.0',
            '~1.20.0',
            null,
            Stability::Stable,
            'Tilde version constraint',
        ];

        yield 'exact version' => [
            '2.12.0',
            '2.12.0',
            null,
            Stability::Stable,
            'Exact version constraint',
        ];

        yield 'greater than or equal' => [
            '>=1.0.0',
            '>=1.0.0',
            null,
            Stability::Stable,
            'Greater than or equal version constraint',
        ];

        yield 'less than' => [
            '<3.0.0',
            '<3.0.0',
            null,
            Stability::Stable,
            'Less than version constraint',
        ];

        // Feature suffix constraints
        yield 'feature suffix' => [
            '^2.12.0-feature',
            '^2.12.0',
            'feature',
            Stability::Preview,
            'Version with feature suffix',
        ];

        yield 'hyphenated feature suffix' => [
            '^2.12.0-my-feature',
            '^2.12.0',
            'my-feature',
            Stability::Preview,
            'Version with hyphenated feature suffix',
        ];

        yield 'single letter feature' => [
            '^2.12.0-x',
            '^2.12.0',
            'x',
            Stability::Preview,
            'Version with single letter feature suffix',
        ];

        // Explicit stability constraints
        yield 'explicit beta stability' => [
            '^2.12.0@beta',
            '^2.12.0',
            null,
            Stability::Beta,
            'Version with explicit beta stability',
        ];

        yield 'explicit alpha stability' => [
            '~1.20.0@alpha',
            '~1.20.0',
            null,
            Stability::Alpha,
            'Version with explicit alpha stability',
        ];

        yield 'explicit stable stability' => [
            '^2.12.0@stable',
            '^2.12.0',
            null,
            Stability::Stable,
            'Version with explicit stable stability',
        ];

        yield 'explicit RC stability' => [
            '^2.12.0@RC',
            '^2.12.0',
            null,
            Stability::RC,
            'Version with explicit RC stability',
        ];

        // Implicit stability constraints (stability keywords as suffixes)
        yield 'implicit beta stability' => [
            '^2.12.0-beta',
            '^2.12.0',
            null,
            Stability::Beta,
            'Version with implicit beta stability',
        ];

        yield 'implicit alpha stability' => [
            '~1.20.0-alpha',
            '~1.20.0',
            null,
            Stability::Alpha,
            'Version with implicit alpha stability',
        ];

        yield 'implicit dev stability' => [
            '^2.12.0-dev',
            '^2.12.0',
            null,
            Stability::Dev,
            'Version with implicit dev stability',
        ];

        yield 'implicit nightly stability' => [
            '^2.12.0-nightly',
            '^2.12.0',
            null,
            Stability::Nightly,
            'Version with implicit nightly stability',
        ];

        // Combined constraints
        yield 'feature with explicit stability' => [
            '^2.12.0-feature@beta',
            '^2.12.0',
            'feature',
            Stability::Beta,
            'Version with feature suffix and explicit stability',
        ];

        yield 'hyphenated feature with stability' => [
            '^2.12.0-my-feature@alpha',
            '^2.12.0',
            'my-feature',
            Stability::Alpha,
            'Version with hyphenated feature suffix and explicit stability',
        ];

        // Complex base versions with feature suffixes
        yield 'complex base with feature' => [
            '^2.12.0-my-beta-feature@stable',
            '^2.12.0',
            'my-beta-feature',
            Stability::Stable,
            'Complex base version with feature suffix',
        ];

        // Edge cases with whitespace
        yield 'constraint with whitespace' => [
            '  ^2.12.0-feature@beta  ',
            '^2.12.0',
            'feature',
            Stability::Beta,
            'Constraint with surrounding whitespace',
        ];

        // Stability at the end of suffix
        yield 'stability at end of suffix' => [
            '^2.12.0-my-feature-beta',
            '^2.12.0',
            'my-feature',
            Stability::Beta,
            'Stability keyword at the end of suffix',
        ];

        // Case sensitivity tests
        yield 'uppercase stability explicit' => [
            '^2.12.0@BETA',
            '^2.12.0',
            null,
            Stability::Beta,
            'Version with uppercase explicit stability',
        ];

        yield 'mixed case stability implicit' => [
            '^2.12.0-Beta',
            '^2.12.0',
            null,
            Stability::Beta,
            'Version with mixed case implicit stability',
        ];

        // Multiple dashes in base version
        yield 'base with multiple dashes' => [
            '^2.12.0-alpha.1-feature',
            '^2.12.0',
            'alpha.1-feature',
            Stability::Preview,
            'Base version with multiple dashes and feature suffix',
        ];

        yield 'feature suffix starting with number' => [
            '^2.12.0-1feature',
            '^2.12.0',
            '1feature',
            Stability::Preview,
            'Feature suffix starting with number',
        ];

        yield 'feature suffix with multiple dashes' => [
            '^2.12.0--my--feature--',
            '^2.12.0',
            'my--feature',
            Stability::Preview,
            'feature suffix with multiple dashes',
        ];
    }

    public static function provideInvalidConstraints(): \Generator
    {
        // Empty constraints
        yield 'empty string' => [
            '',
            'Version constraint cannot be empty',
            'Empty constraint string',
        ];

        yield 'whitespace only' => [
            '   ',
            'Version constraint cannot be empty',
            'Whitespace-only constraint string',
        ];

        // Invalid base version format
        yield 'invalid base version no numbers' => [
            'invalid',
            'Invalid base version format: invalid',
            'Base version without numbers',
        ];

        yield 'invalid base version starting with letter' => [
            'abc1.2.3',
            'Invalid base version format: abc1.2.3',
            'Base version starting with letters',
        ];

        yield 'feature suffix with special characters' => [
            '^2.12.0-feature@test',
            'Invalid stability level: @test',
            'Feature suffix with special characters',
        ];

        yield 'feature suffix with spaces' => [
            '^2.12.0-my feature',
            'Invalid feature suffix format: my feature.',
            'Feature suffix with spaces',
        ];

        // Invalid stability
        yield 'invalid explicit stability' => [
            '^2.12.0@invalid',
            'Invalid stability level: @invalid',
            'Invalid explicit stability',
        ];

        yield 'empty stability' => [
            '^2.12.0@',
            'Invalid stability level: @',
            'Empty explicit stability',
        ];

        // Multiple @ symbols
        yield 'multiple stability indicators' => [
            '^2.12.0@beta@alpha',
            'Invalid stability level: @beta@alpha',
            'Multiple @ symbols in constraint',
        ];
    }

    public static function provideComparableConstraints(): \Generator
    {
        yield ['^1.0-priority@dev', '1.3.1-priority.0', true];
        yield ['^1.0-priority', '1.3.1-priority.0', true];
        yield ['^1.0-priority@rc', '1.3.1-RC1-priority.0', true];
        yield ['^1.0-priority@RC', '1.3.1-RC1-priority.0', true];
        yield ['^1.0-priority@RC', '1.3.1-priority.0', false];
    }

    #[DataProvider('provideValidConstraints')]
    public function testFromConstraintStringWithValidInput(
        string $constraint,
        string $expectedBaseVersion,
        ?string $expectedFeatureSuffix,
        Stability $expectedStability,
        string $description,
    ): void {
        // Act
        $result = Constraint::fromConstraintString($constraint);

        // Assert
        self::assertSame($expectedBaseVersion, $result->versionConstraint, "Base version for: {$description}");
        self::assertSame($expectedFeatureSuffix, $result->featureSuffix, "Feature suffix for: {$description}");
        self::assertSame($expectedStability, $result->minimumStability, "Stability for: {$description}");
    }

    #[DataProvider('provideInvalidConstraints')]
    public function testFromConstraintStringWithInvalidInput(
        string $constraint,
        string $expectedExceptionMessage,
        string $description,
    ): void {
        // Assert
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage($expectedExceptionMessage);

        // Act
        Constraint::fromConstraintString($constraint);
    }

    public function testGetBaseConstraint(): void
    {
        // Arrange
        $constraint = Constraint::fromConstraintString('^2.12.0', 'feature', Stability::Beta);

        // Assert
        self::assertSame('^2.12.0', $constraint->versionConstraint);
    }

    public function testToStringWithBaseVersionOnly(): void
    {
        // Arrange
        $constraint = Constraint::fromConstraintString('^2.12.0', null, Stability::Stable);

        // Act
        $result = (string) $constraint;

        // Assert
        self::assertSame('^2.12.0', $result);
    }

    public function testToStringWithFeatureSuffixAndCustomStability(): void
    {
        // Arrange
        $constraint = Constraint::fromConstraintString('^2.12.0-feature@beta');

        // Act
        $result = (string) $constraint;

        // Assert
        self::assertSame('^2.12.0-feature@beta', $result);
    }

    #[DataProvider('provideComparableConstraints')]
    public function testIsSatisfiedBy(string $constraint, string $version, bool $expected): void
    {
        // Arrange
        $constraintObj = Constraint::fromConstraintString($constraint);
        $versionObj = Version::fromVersionString($version);

        // Act
        $result = $constraintObj->isSatisfiedBy($versionObj);

        // Assert
        self::assertSame($expected, $result, "Constraint: {$constraint}, Version: {$version}");
    }
}
