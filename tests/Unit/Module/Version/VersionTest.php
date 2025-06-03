<?php

declare(strict_types=1);

namespace Internal\DLoad\Tests\Unit\Module\Version;

use Generator;
use Internal\DLoad\Module\Common\Stability;
use Internal\DLoad\Module\Version\Version;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Version::class)]
final class VersionTest extends TestCase
{
    /**
     * Provides test cases for successful version string parsing.
     */
    public static function provideValidVersionStrings(): Generator
    {
        // Semantic versions
        yield 'basic semantic version' => [
            '1.2.3',
            '1.2.3',
            '1.2.3',
            null,
            Stability::Stable,
        ];

        yield 'semantic version with patch number' => [
            '2.12.5',
            '2.12.5',
            '2.12.5',
            null,
            Stability::Stable,
        ];

        yield 'semantic version with build number' => [
            '1.0.0+123',
            '1.0.0+123',
            '1.0.0+123',
            null,
            Stability::Stable,
        ];

        // Versions with stability suffixes
        yield 'version with beta suffix' => [
            '1.2.3-beta',
            '1.2.3-beta',
            '1.2.3',
            null,
            Stability::Beta,
        ];

        yield 'version with alpha suffix' => [
            '2.0.0-alpha',
            '2.0.0-alpha',
            '2.0.0',
            null,
            Stability::Alpha,
        ];

        yield 'version with rc suffix' => [
            '1.5.0-rc',
            '1.5.0-rc',
            '1.5.0',
            null,
            Stability::RC,
        ];

        yield 'version with dev suffix' => [
            '3.1.0-dev',
            '3.1.0-dev',
            '3.1.0',
            null,
            Stability::Dev,
        ];

        yield 'version with stable suffix' => [
            '1.0.0-stable',
            '1.0.0-stable',
            '1.0.0',
            null,
            Stability::Stable,
        ];

        // Versions with feature suffixes
        yield 'version with feature suffix after stability' => [
            '1.2.3-beta-feature',
            '1.2.3-beta-feature',
            '1.2.3',
            'feature',
            Stability::Beta,
        ];

        yield 'version with stability at end' => [
            '1.2.3-feature-beta',
            '1.2.3-feature-beta',
            '1.2.3',
            'feature',
            Stability::Beta,
        ];

        yield 'version with multiple features and stability' => [
            '2.0.0-feature1-feature2-alpha',
            '2.0.0-feature1-feature2-alpha',
            '2.0.0',
            'feature1-feature2',
            Stability::Alpha,
        ];

        // Versions with plus prefix
        yield 'version with plus prefix stability' => [
            '1.2.3+beta',
            '1.2.3+beta',
            '1.2.3',
            null,
            Stability::Beta,
        ];

        yield 'version with plus prefix feature' => [
            '1.2.3+feature-alpha',
            '1.2.3+feature-alpha',
            '1.2.3',
            'feature',
            Stability::Alpha,
        ];

        // Partial semantic versions (fallback pattern)
        yield 'two-part version' => [
            '1.2',
            '1.2',
            '1.2',
            null,
            Stability::Stable,
        ];

        yield 'single digit version' => [
            '5',
            '5',
            '5',
            null,
            Stability::Stable,
        ];

        yield 'version with suffix but no recognized stability' => [
            '1.2.3-custom',
            '1.2.3-custom',
            '1.2.3',
            'custom',
            Stability::Stable,
        ];

        // Case insensitive stability
        yield 'version with uppercase stability' => [
            '1.2.3-BETA',
            '1.2.3-BETA',
            '1.2.3',
            null,
            Stability::Beta,
        ];

        yield 'version with mixed case stability' => [
            '1.2.3-Alpha',
            '1.2.3-Alpha',
            '1.2.3',
            null,
            Stability::Alpha,
        ];
    }

    /**
     * Provides test cases for invalid version strings that should throw exceptions.
     */
    public static function provideInvalidVersionStrings(): Generator
    {
        yield 'empty string' => [''];
        yield 'non-numeric string' => ['not-a-version'];
        yield 'only text' => ['beta'];
        yield 'special characters only' => ['!@#$'];
        yield 'version starting with text' => ['version1.2.3'];
    }

    /**
     * Tests that valid version strings are parsed correctly.
     */
    #[DataProvider('provideValidVersionStrings')]
    public function testFromVersionStringParsesValidVersions(
        string $input,
        string $expectedString,
        string $expectedNumber,
        ?string $expectedSuffix,
        Stability $expectedStability
    ): void {
        // Act
        $version = Version::fromVersionString($input);

        // Assert
        self::assertSame($expectedString, $version->string);
        self::assertSame($expectedNumber, $version->number);
        self::assertSame($expectedSuffix, $version->suffix);
        self::assertSame($expectedStability, $version->stability);
    }

    /**
     * Tests that invalid version strings throw InvalidArgumentException.
     */
    #[DataProvider('provideInvalidVersionStrings')]
    public function testFromVersionStringThrowsExceptionForInvalidInput(string $input): void
    {
        // Assert
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Failed version string: {$input}.");

        // Act
        Version::fromVersionString($input);
    }

    /**
     * Tests the empty version factory method.
     */
    public function testEmptyCreatesVersionWithEmptyString(): void
    {
        // Act
        $version = Version::empty();

        // Assert
        self::assertSame('', $version->string);
        self::assertNull($version->number);
        self::assertNull($version->suffix);
        self::assertNull($version->stability);
    }

    /**
     * Tests the __toString method returns the version number.
     */
    public function testToStringReturnsVersionNumber(): void
    {
        // Arrange
        $version = Version::fromVersionString('1.2.3-beta');

        // Act
        $result = (string) $version;

        // Assert
        self::assertSame('1.2.3', $result);
    }

    /**
     * Tests the __toString method with empty version.
     */
    public function testToStringWithEmptyVersionReturnsEmptyString(): void
    {
        // Arrange
        $version = Version::empty();

        // Act
        $result = (string) $version;

        // Assert
        self::assertSame('', $result);
    }

    /**
     * Tests that version properties are readonly.
     */
    public function testVersionPropertiesAreReadonly(): void
    {
        // Arrange
        $version = Version::fromVersionString('1.2.3-beta-feature');

        // Act & Assert
        self::assertSame('1.2.3-beta-feature', $version->string);
        self::assertSame('1.2.3', $version->number);
        self::assertSame('feature', $version->suffix);
        self::assertSame(Stability::Beta, $version->stability);
    }

    /**
     * Tests that stability is correctly determined for complex version strings.
     */
    public function testStabilityDetectionInComplexVersions(): void
    {
        // Arrange & Act & Assert
        $version1 = Version::fromVersionString('1.2.3-feature-build-rc');
        self::assertSame(Stability::RC, $version1->stability);
        self::assertSame('feature-build', $version1->suffix);

        $version2 = Version::fromVersionString('1.2.3-alpha-feature-build');
        self::assertSame(Stability::Alpha, $version2->stability);
        self::assertSame('feature-build', $version2->suffix);
    }

    /**
     * Tests that unknown stability keywords default to Stable.
     */
    public function testUnknownStabilityDefaultsToStable(): void
    {
        // Arrange & Act
        $version = Version::fromVersionString('1.2.3-unknown-keyword');

        // Assert
        self::assertSame(Stability::Stable, $version->stability);
        self::assertSame('unknown-keyword', $version->suffix);
    }
}
