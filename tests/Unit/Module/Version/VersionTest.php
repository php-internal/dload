<?php

declare(strict_types=1);

namespace Internal\DLoad\Tests\Unit\Module\Version;

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
    public static function provideValidVersionStrings(): \Generator
    {
        // Semantic versions
        yield 'basic semantic version' => ['1.2.3', '1.2.3', '1.2.3', null, Stability::Stable];
        yield 'semantic version with patch number' => ['2.12.5', '2.12.5', '2.12.5', null, Stability::Stable];
        yield 'semantic version with build number' => ['1.0.0+123', '1.0.0+123', '1.0.0+123', null, Stability::Stable];

        // Versions with stability suffixes
        yield 'version with beta suffix' => ['1.2.3-beta', '1.2.3-beta', '1.2.3', null, Stability::Beta];
        yield 'version with alpha suffix' => ['2.0.0-alpha', '2.0.0-alpha', '2.0.0', null, Stability::Alpha];
        yield 'version with rc suffix' => ['1.5.0-rc', '1.5.0-rc', '1.5.0', null, Stability::RC];
        yield 'version with dev suffix' => ['3.1.0-dev', '3.1.0-dev', '3.1.0', null, Stability::Dev];
        yield 'version with stable suffix' => ['1.0.0-stable', '1.0.0-stable', '1.0.0', null, Stability::Stable];

        // Versions with feature suffixes
        yield 'version with feature suffix after stability' => ['1.2.3-beta-feature', '1.2.3-beta-feature', '1.2.3', 'feature', Stability::Beta];
        yield 'version with stability at end' => ['1.2.3-feature-beta', '1.2.3-feature-beta', '1.2.3', 'feature', Stability::Beta];
        yield 'version with multiple features and stability' => ['2.0.0-feature1-feature2-alpha', '2.0.0-feature1-feature2-alpha', '2.0.0', 'feature1-feature2', Stability::Alpha];

        // Versions with plus prefix
        yield 'version with plus prefix stability' => ['1.2.3+beta', '1.2.3+beta', '1.2.3', null, Stability::Beta];
        yield 'version with plus prefix feature' => ['1.2.3+feature-alpha', '1.2.3+feature-alpha', '1.2.3', 'feature', Stability::Alpha];

        // Partial semantic versions (fallback pattern)
        yield 'two-part version' => ['1.2', '1.2', '1.2', null, Stability::Stable];
        yield 'single digit version' => ['5', '5', '5', null, Stability::Stable];
        yield 'version with suffix but no recognized stability' => ['1.2.3-custom', '1.2.3-custom', '1.2.3', 'custom', Stability::Dev];

        // Case insensitive stability
        yield 'version with uppercase stability' => ['1.2.3-BETA', '1.2.3-BETA', '1.2.3', null, Stability::Beta];
        yield 'version with mixed case stability' => ['1.2.3-Alpha', '1.2.3-Alpha', '1.2.3', null, Stability::Alpha];
    }

    /**
     * Provides test cases for invalid version strings that should throw exceptions.
     */
    public static function provideInvalidVersionStrings(): \Generator
    {
        yield 'empty string' => [''];
        yield 'non-numeric string' => ['not-a-version'];
        yield 'only text' => ['beta'];
        yield 'special characters only' => ['!@#$'];
        yield 'version starting with text' => ['version1.2.3'];
        yield 'dev-master' => ['dev-master'];
        yield 'dev-feature+issue-1' => ['dev-feature+issue-1'];
        yield '1.0.0-alpha11+cs-1.1.0' => ['1.0.0-alpha11+cs-1.1.0'];
        yield '1.0.0-beta#comment-part' => ['1.0.0-beta#comment-part'];
    }

    /**
     * Data provider for versions and their expected stability levels
     */
    public static function provideVersionsAndExpectedStability(): \Generator
    {
        // Composer cases
        yield ['1', Stability::Stable, ''];
        yield ['1.0', Stability::Stable, ''];
        yield ['3.2.1', Stability::Stable, ''];
        yield ['v3.2.1', Stability::Stable, ''];
        yield ['v2.0.x-dev', Stability::Dev, ''];
        yield ['v2.0.x-dev#abc123', Stability::Dev, ''];
        yield ['3.0-RC2', Stability::RC, ''];
        yield ['3.1.2-dev', Stability::Dev, ''];
        yield ['3.1.2-p1', Stability::Dev, 'Composer expects Stable here'];
        yield ['3.1.2-pl2', Stability::Dev, 'Composer expects Stable here'];
        yield ['3.1.2-patch', Stability::Dev, 'Composer expects Stable here'];
        yield ['3.1.2-alpha5', Stability::Alpha, ''];
        yield ['3.1.2-beta', Stability::Beta, ''];
        yield ['2.0B1', Stability::Beta, ''];
        yield ['1.2.0a1', Stability::Alpha, ''];
        yield ['1.2_a1', Stability::Alpha, ''];
        yield ['2.0.0rc1', Stability::RC, ''];
        yield ['1-2_dev', Stability::Dev, ''];

        // Dev versions with suffix
        yield 'dev suffix - direct' => ['1.0.0-dev', Stability::Dev, 'Version with dev suffix'];
        yield 'dev suffix - with number' => ['2.3.4-dev', Stability::Dev, 'Version with dev suffix'];

        // Dev versions with stability and dev suffix
        yield 'beta with dev suffix 1' => ['1.0.0-beta.dev', Stability::Dev, 'Version with stability and dev suffix'];
        yield 'beta with dev suffix 2' => ['1.0.0-beta-dev', Stability::Dev, 'Version with stability and dev suffix'];
        yield 'alpha with dev suffix 1' => ['2.0.0-alpha.1.dev', Stability::Dev, 'Version with stability and dev suffix'];
        yield 'alpha with dev suffix 2' => ['2.0.0-alpha.1-dev', Stability::Dev, 'Version with stability and dev suffix'];
        yield 'rc with dev suffix 1' => ['3.0.0-rc.2-dev', Stability::Dev, 'Version with stability and dev suffix'];

        // Named stability levels
        yield 'stable version' => ['1.0.0', Stability::Stable, 'Stable version'];
        yield 'RC version' => ['1.0.0-RC1', Stability::RC, 'RC version'];
        yield 'pre version' => ['1.0.0-pre.3', Stability::Pre, 'Pre version'];
        yield 'beta version' => ['1.0.0-beta4', Stability::Beta, 'Beta version'];
        yield 'preview version' => ['1.0.0-preview5', Stability::Preview, 'Preview version'];
        yield 'alpha version' => ['1.0.0-alpha6', Stability::Alpha, 'Alpha version'];
        yield 'unstable version' => ['1.0.0-unstable7', Stability::Unstable, 'Unstable version'];
        yield 'snapshot version' => ['1.0.0-snapshot', Stability::Snapshot, 'Snapshot version'];
        yield 'nightly version' => ['1.0.0-nightly20250503', Stability::Nightly, 'Nightly version'];

        // Abbreviated stability indicators
        yield 'alpha abbreviated' => ['1.0.0a1', Stability::Alpha, 'Alpha abbreviated'];
        yield 'beta abbreviated' => ['1.0.0b2', Stability::Beta, 'Beta abbreviated'];
        yield 'unknown abbreviated' => ['1.0.0x3', Stability::Dev, 'Unknown abbreviated (defaults to Stable)'];

        // Different separators
        yield 'dash separator' => ['1.0.0-beta1', Stability::Beta, 'Version with dash separator'];
        yield 'dot separator' => ['1.0.0.beta2', Stability::Beta, 'Version with dot separator'];
        yield 'underscore separator' => ['1.0.0_beta3', Stability::Beta, 'Version with underscore separator'];

        // Real cases
        yield 'real case 2' => ['v1.3.1-nexus-cancellation.0', Stability::Dev, 'Temporal cancellation version'];
        yield 'real case 3' => ['v1.3.0', Stability::Stable, 'Stable version'];
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
        Stability $expectedStability,
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
        self::assertSame('1.2.3-beta', $result);
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
     * Tests that parseStability correctly identifies stability from version strings
     */
    #[DataProvider('provideVersionsAndExpectedStability')]
    public function testParseStability(string $version, Stability $expected, string $description): void
    {
        // Act
        $version = Version::fromVersionString($version);
        $stability = $version->stability;

        // Assert
        self::assertSame(
            $expected,
            $stability,
            \sprintf('%s: Version "%s" should be recognized as %s stability', $description, $version, $expected->value),
        );
    }

    /**
     * Tests that parseStability correctly identifies stability from version strings
     */
    #[DataProvider('provideInvalidVersionStrings')]
    public function testParseStabilityInvalidCases(string $version): void
    {
        // Assert
        $this->expectException(\InvalidArgumentException::class);

        // Act
        $version = Version::fromVersionString($version);
    }
}
