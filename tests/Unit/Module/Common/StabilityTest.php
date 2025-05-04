<?php

declare(strict_types=1);

namespace Internal\DLoad\Tests\Unit\Module\Common;

use Internal\DLoad\Module\Common\Stability;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Stability::class)]
final class StabilityTest extends TestCase
{
    /**
     * Data provider for versions and their expected stability levels
     */
    public static function provideVersionsAndExpectedStability(): \Generator
    {
        // Dev versions with prefix
        yield 'dev prefix - main' => ['dev-main', Stability::Dev, 'Dev-prefixed version'];
        yield 'dev prefix - master' => ['dev-master', Stability::Dev, 'Dev-prefixed version'];
        yield 'dev prefix - feature' => ['dev-feature-branch', Stability::Dev, 'Dev-prefixed version'];

        // Dev versions with suffix
        yield 'dev suffix - direct' => ['1.0.0-dev', Stability::Dev, 'Version with dev suffix'];
        yield 'dev suffix - with number' => ['2.3.4-dev', Stability::Dev, 'Version with dev suffix'];

        // Version with comment
        yield 'version with comment' => ['1.0.0-beta#comment-part', Stability::Beta, 'Version with comment'];

        // Dev versions with stability and dev suffix
        yield 'beta with dev suffix 1' => ['1.0.0-beta.dev', Stability::Dev, 'Version with stability and dev suffix'];
        yield 'beta with dev suffix 2' => ['1.0.0-beta-dev', Stability::Dev, 'Version with stability and dev suffix'];
        yield 'alpha with dev suffix 1' => ['2.0.0-alpha.1.dev', Stability::Dev, 'Version with stability and dev suffix'];
        yield 'alpha with dev suffix 2' => ['2.0.0-alpha.1-dev', Stability::Dev, 'Version with stability and dev suffix'];
        yield 'rc with dev suffix 1' => ['3.0.0-rc.2-dev', Stability::Dev, 'Version with stability and dev suffix'];

        // Named stability levels
        yield 'stable version' => ['1.0.0', Stability::Stable, 'Stable version'];
        yield 'RC version' => ['1.0.0-RC1', Stability::RC, 'RC version'];
        yield 'priority version' => ['1.0.0-priority2', Stability::Priority, 'Priority version'];
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
        yield 'unknown abbreviated' => ['1.0.0x3', Stability::Stable, 'Unknown abbreviated (defaults to Stable)'];

        // Different separators
        yield 'dash separator' => ['1.0.0-beta1', Stability::Beta, 'Version with dash separator'];
        yield 'dot separator' => ['1.0.0.beta2', Stability::Beta, 'Version with dot separator'];
        yield 'underscore separator' => ['1.0.0_beta3', Stability::Beta, 'Version with underscore separator'];

        // Real cases
        yield 'real case 1' => ['v1.0.0-priority.0', Stability::Priority, 'Temporal priority version'];
    }

    /**
     * Tests that parseStability correctly identifies stability from version strings
     */
    #[DataProvider('provideVersionsAndExpectedStability')]
    public function testParseStability(string $version, Stability $expected, string $description): void
    {
        // Act
        $stability = Stability::parse($version);

        // Assert
        self::assertSame(
            $expected,
            $stability,
            \sprintf('%s: Version "%s" should be recognized as %s stability', $description, $version, $expected->value),
        );
    }
}
