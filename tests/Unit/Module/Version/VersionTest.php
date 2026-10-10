<?php

declare(strict_types=1);

namespace Internal\DLoad\Tests\Unit\Module\Version;

use Internal\DLoad\Module\Common\Stability;
use Internal\DLoad\Module\Version\Version;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Data\DataProvider;
use Testo\Expect;
use Testo\Test;

#[Covers(Version::class)]
final class VersionTest
{
    public static function providePreReleases(): \Generator
    {
        yield 'dotted beta' => ['v3.5.0-beta.1', Stability::Beta, 1];
        yield 'release candidate' => ['v3.5.0-RC1', Stability::RC, 1];
        yield 'dotted release candidate' => ['2025.1.0-rc.2', Stability::RC, 2];
        yield 'nightly date' => ['1.0.0-nightly20250503', Stability::Nightly, 20250503];
        yield 'beta abbreviation' => ['1.0.0b2', Stability::Beta, 2];
        yield 'no separator' => ['2.0.0rc1', Stability::RC, 1];
        yield 'underscore separator' => ['1.0.0-rc_1', Stability::RC, 1];
        yield 'bare stability' => ['1.2.3-beta', Stability::Beta, 0];
        yield 'stability before a feature' => ['1.2.3-beta.3-feature', Stability::Beta, 3];
        yield 'dev branch' => ['v2.0.x-dev', Stability::Dev, 0];
        yield 'stable release' => ['v3.5.0', Stability::Stable, 0];
        yield 'feature only' => ['1.2.3-custom', Stability::Preview, 0];
    }

    public static function provideOrderedVersions(): \Generator
    {
        yield 'pre-release numbers' => ['1.0.0-beta.2', '1.0.0-beta.10'];
        yield 'stability weight' => ['1.0.0-beta.10', '1.0.0-RC1'];
        yield 'final after its release candidate' => ['1.0.0-rc.9', '1.0.0'];
        yield 'nightly first' => ['1.0.0-nightly20250503', '1.0.0-alpha.1'];
        yield 'version number first' => ['1.0.0', '1.0.1-alpha.1'];
        yield 'feature build before the final' => ['1.0.0-custom', '1.0.0'];
        yield 'upper case release candidate before the final' => ['1.0.0-RC1', '1.0.0'];
        yield 'underscore spelling' => ['1.0.0-rc_1', '1.0.0-rc_2'];
        yield 'fourth number part' => ['1.2.3.4', '1.2.3.5'];
        yield 'fourth number part over ten' => ['1.2.3.9', '1.2.3.10'];
        yield 'three parts before four' => ['1.2.3', '1.2.3.4'];
        yield 'four parts before the next patch' => ['1.2.3.4', '1.2.4'];
        yield 'feature build number' => ['1.3.1-RC1-priority.0', '1.3.1-RC1-priority.1'];
        yield 'number after the pre-release' => ['2.0.0-beta.1.2', '2.0.0-beta.1.3'];
        yield 'number after the pre-release before the final' => ['2.0.0-beta.1.2', '2.0.0'];
    }

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
        yield 'preview is not read as pre' => ['1.0.0-preview-foo', '1.0.0-preview-foo', '1.0.0', 'foo', Stability::Preview];
        yield 'version with multiple features and stability' => ['2.0.0-feature1-feature2-alpha', '2.0.0-feature1-feature2-alpha', '2.0.0', 'feature1-feature2', Stability::Alpha];

        // Versions with plus prefix
        yield 'version with plus prefix stability' => ['1.2.3+beta', '1.2.3+beta', '1.2.3', null, Stability::Beta];
        yield 'version with plus prefix feature' => ['1.2.3+feature-alpha', '1.2.3+feature-alpha', '1.2.3', 'feature', Stability::Alpha];

        // Partial semantic versions (fallback pattern)
        yield 'two-part version' => ['1.2', '1.2', '1.2', null, Stability::Stable];
        yield 'single digit version' => ['5', '5', '5', null, Stability::Stable];
        yield 'version with suffix but no recognized stability' => ['1.2.3-custom', '1.2.3-custom', '1.2.3', 'custom', Stability::Preview];

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
        yield ['3.1.2-p1', Stability::Preview, 'Composer expects Stable here'];
        yield ['3.1.2-pl2', Stability::Preview, 'Composer expects Stable here'];
        yield ['3.1.2-patch', Stability::Preview, 'Composer expects Stable here'];
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
        yield 'unknown abbreviated' => ['1.0.0x3', Stability::Preview, 'Unknown abbreviated (defaults to Stable)'];

        // Different separators
        yield 'dash separator' => ['1.0.0-beta1', Stability::Beta, 'Version with dash separator'];
        yield 'dot separator' => ['1.0.0.beta2', Stability::Beta, 'Version with dot separator'];
        yield 'underscore separator' => ['1.0.0_beta3', Stability::Beta, 'Version with underscore separator'];

        // Real cases
        yield 'real case 2' => ['v1.3.1-nexus-cancellation.0', Stability::Preview, 'Temporal cancellation version'];
        yield 'real case 3' => ['v1.3.0', Stability::Stable, 'Stable version'];
    }

    #[DataProvider('providePreReleases')]
    #[Test]
    public function preReleaseKeepsTheStabilityWithItsNumber(string $input, Stability $stability, int $number): void
    {
        $version = Version::fromVersionString($input);

        Assert::same($version->preRelease?->stability, $stability);
        Assert::same($version->preRelease?->number, $number);
        Assert::same($version->stability, $stability);
    }

    #[DataProvider('provideOrderedVersions')]
    #[Test]
    public function compareOrdersByNumberThenByPreRelease(string $older, string $newer): void
    {
        $a = Version::fromVersionString($older);
        $b = Version::fromVersionString($newer);

        Assert::same($a->compare($b), -1);
        Assert::same($b->compare($a), 1);
    }

    #[Test]
    public function compareTreatsSpellingsOfOnePreReleaseAsEqual(): void
    {
        Assert::same(Version::fromVersionString('v3.5.0-rc.1')->compare(Version::fromVersionString('3.5.0-RC1')), 0);
    }

    #[Test]
    public function emptyVersionComesFirst(): void
    {
        Assert::same(Version::empty()->compare(Version::fromVersionString('0.0.1')), -1);
    }

    /**
     * Tests that valid version strings are parsed correctly.
     */
    #[DataProvider('provideValidVersionStrings')]
    #[Test]
    public function fromVersionStringParsesValidVersions(
        string $input,
        string $expectedString,
        string $expectedNumber,
        ?string $expectedSuffix,
        Stability $expectedStability,
    ): void {
        $version = Version::fromVersionString($input);

        Assert::same($version->string, $expectedString);
        Assert::same($version->number, $expectedNumber);
        Assert::same($version->suffix, $expectedSuffix);
        Assert::same($version->stability, $expectedStability);
    }

    /**
     * Tests that invalid version strings throw InvalidArgumentException.
     */
    #[DataProvider('provideInvalidVersionStrings')]
    #[Test]
    public function fromVersionStringThrowsExceptionForInvalidInput(string $input): void
    {
        Expect::exception(\InvalidArgumentException::class)->withMessage("Failed version string: {$input}.");

        Version::fromVersionString($input);
    }

    /**
     * Tests the empty version factory method.
     */
    #[Test]
    public function emptyCreatesVersionWithEmptyString(): void
    {
        $version = Version::empty();

        Assert::same($version->string, '');
        Assert::null($version->number);
        Assert::null($version->suffix);
        Assert::null($version->stability);
    }

    /**
     * Tests the __toString method returns the version number.
     */
    #[Test]
    public function toStringReturnsVersionNumber(): void
    {
        $version = Version::fromVersionString('1.2.3-beta');

        $result = (string) $version;

        Assert::same($result, '1.2.3-beta');
    }

    /**
     * Tests the __toString method with empty version.
     */
    #[Test]
    public function toStringWithEmptyVersionReturnsEmptyString(): void
    {
        $version = Version::empty();

        $result = (string) $version;

        Assert::same($result, '');
    }

    /**
     * Tests that version properties are readonly.
     */
    #[Test]
    public function versionPropertiesAreReadonly(): void
    {
        $version = Version::fromVersionString('1.2.3-beta-feature');

        Assert::same($version->string, '1.2.3-beta-feature');
        Assert::same($version->number, '1.2.3');
        Assert::same($version->suffix, 'feature');
        Assert::same($version->stability, Stability::Beta);
    }

    /**
     * Tests that stability is correctly determined for complex version strings.
     */
    #[Test]
    public function stabilityDetectionInComplexVersions(): void
    {
        $version1 = Version::fromVersionString('1.2.3-feature-build-rc');
        Assert::same($version1->stability, Stability::RC);
        Assert::same($version1->suffix, 'feature-build');

        $version2 = Version::fromVersionString('1.2.3-alpha-feature-build');
        Assert::same($version2->stability, Stability::Alpha);
        Assert::same($version2->suffix, 'feature-build');
    }

    /**
     * Tests that parseStability correctly identifies stability from version strings
     */
    #[DataProvider('provideVersionsAndExpectedStability')]
    #[Test]
    public function parseStability(string $version, Stability $expected, string $description): void
    {
        $version = Version::fromVersionString($version);
        $stability = $version->stability;

        Assert::same($stability, $expected, \sprintf('%s: Version "%s" should be recognized as %s stability', $description, $version, $expected->value));
    }

    /**
     * Tests that parseStability correctly identifies stability from version strings
     */
    #[DataProvider('provideInvalidVersionStrings')]
    #[Test]
    public function parseStabilityInvalidCases(string $version): void
    {
        Expect::exception(\InvalidArgumentException::class);

        $version = Version::fromVersionString($version);
    }
}
