<?php

declare(strict_types=1);

namespace Internal\DLoad\Tests\Unit\Module\Version;

use Composer\Semver\Semver;
use Internal\DLoad\Module\Version\Range;
use Internal\DLoad\Module\Version\Version;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Test;

/**
 * Compares {@see Range} with Composer's `Semver::satisfies()` on the syntax both read.
 *
 * Intentional differences stay out of the table: a bound without a pre-release counts every build of its
 * number, while Composer orders `1.2.3-beta` below `1.2.3`; numbers Composer cannot read (five parts or more,
 * a major part over five digits) match.
 */
#[Covers(Range::class)]
final class RangeComposerTest
{
    private const BASES = ['0', '1', '0.0', '0.3', '1.2', '0.0.0', '0.0.3', '0.3.2', '1.2.3', '2.0.0', '1.2.3.4', '0.0.3.4'];
    private const OPERATORS = ['', '=', '==', '!=', '>', '>=', '<', '<=', '^', '~', 'v', '>= '];
    private const CONSTRAINTS = [
        '*', '1.*', '1.x', '1.2.*', '1.2.3.*', '0.*', '1.*.*', 'v1.*',
        '1.0 - 2.0', '1 - 2', '1.2.3 - 2.3', '1.0.0 - 2.0.0', '1.2 - 2.3.4', '0.3 - 1.2.3.4',
        '^1.2 || ^2.0', '1.0|2.0', '~0.3|^1', '>=1.0 <2.0', '>=1.0,<2.0', '>=1.0, <2.0', '>1.0 <=1.2.3',
        '>=1.0 <2.0 || >=3.0', '^1.0 !=1.2.3', '>= 1.0 < 2.0', '1.2.3+5', '^1.2.3+5',
    ];
    private const VERSIONS = [
        '0', '0.0.0', '0.0.1', '0.0.3', '0.0.3.4', '0.0.3.9', '0.0.4', '0.1', '0.2.9', '0.3', '0.3.1', '0.3.2', '0.3.9',
        '0.4', '0.9.9', '1', '1.0.1', '1.1', '1.2', '1.2.2', '1.2.3', '1.2.3.0', '1.2.3.1', '1.2.3.4', '1.2.3.5',
        '1.2.4', '1.2.10', '1.3', '1.9.9', '1.99.99.99', '2', '2.0.1', '2.3', '2.3.4', '2.3.5', '2.4', '3', '3.1',
        '10.0.0', '99999.0.0', '1.2.3+5', '1.2.4+1',
    ];

    /** Pre-release bounds and the versions they decide between. */
    private const PRE_RELEASE_CONSTRAINTS = [
        '1.2.3-beta.1', '=1.2.3-RC1', '!=1.2.3-beta.1', '>=1.2.3-beta.1', '>1.2.3-beta.1', '<1.2.3-beta.2',
        '<=1.2.3-rc.1', '^1.2.3-RC1', '~1.2.3-alpha.2', '^0.3.2-beta.1', '1.0 - 1.2.3-beta.2',
    ];

    private const PRE_RELEASE_VERSIONS = [
        '1.2.2', '1.2.2-RC1', '1.2.3-alpha.1', '1.2.3-alpha.2', '1.2.3-beta.1', '1.2.3-beta.2', '1.2.3-RC1',
        '1.2.3-RC2', '1.2.3', '1.2.4-alpha.1', '1.2.4', '1.3.0-beta.1', '2.0.0-beta.1', '2.0.0', '0.3.2-beta.1',
        '0.3.2', '0.3.9', '0.4.0-beta.1',
    ];

    #[Test]
    public function agreesWithComposerOnReleases(): void
    {
        $constraints = self::CONSTRAINTS;
        foreach (self::BASES as $base) {
            foreach (self::OPERATORS as $operator) {
                $constraints[] = $operator . $base;
            }
        }

        $this->assertAgreement($constraints, self::VERSIONS);
    }

    #[Test]
    public function agreesWithComposerOnPreReleaseBounds(): void
    {
        $this->assertAgreement(self::PRE_RELEASE_CONSTRAINTS, self::PRE_RELEASE_VERSIONS);
    }

    /**
     * @param list<string> $constraints
     * @param list<non-empty-string> $versions
     */
    private function assertAgreement(array $constraints, array $versions): void
    {
        $disagreements = [];
        foreach ($constraints as $constraint) {
            $range = Range::fromString($constraint);
            foreach ($versions as $version) {
                $expected = Semver::satisfies($version, $constraint);
                $range->isSatisfiedBy(Version::fromVersionString($version)) === $expected
                    or $disagreements[] = "{$constraint} on {$version}: Composer says " . \var_export($expected, true);
            }
        }

        Assert::same($disagreements, []);
    }
}
