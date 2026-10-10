<?php

declare(strict_types=1);

namespace Internal\DLoad\Tests\Unit\Module\Version;

use Internal\DLoad\Module\Version\Comparison;
use Internal\DLoad\Module\Version\Operator;
use Internal\DLoad\Module\Version\Range;
use Internal\DLoad\Module\Version\Version;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Data\DataProvider;
use Testo\Expect;
use Testo\Test;

#[Covers(Range::class)]
#[Covers(Comparison::class)]
#[Covers(Operator::class)]
final class RangeTest
{
    private const OPERATORS = ['=', '!=', '>', '>=', '<', '<='];

    /** Versions in ascending order, a group of equal versions in one row. */
    private const ORDERED_VERSIONS = [
        ['0.0.1'],
        ['0.9.9-1'],
        ['1.0.0-nightly20250101'],
        ['1.0.0-alpha.1', '1.0.0-a1'],
        ['1.0.0-alpha.2'],
        ['1.0.0-preview'],
        ['1.0.0-custom'],
        ['1.0.0-beta.1', '1.0.0.0-beta.1', '1.0.0b1'],
        ['1.0.0-beta.1-linux'],
        ['1.0.0-beta.1.2'],
        ['1.0.0-beta.2'],
        ['1.0.0-RC1', '1.0.0-rc.1'],
        ['1.0.0-RC1-linux'],
        ['1.0.0', '1.0', '1', '1.0.0.0.0'],
        ['1.0.0-1'],
        ['1.0.0.1-beta.1'],
        ['1.0.0.1'],
        ['1.0.0.1.5'],
        ['1.0.0-2'],
        ['1.0.1-RC1'],
        ['1.0.1'],
        ['1.2.3.4.5'],
        ['2.0.0-beta.1'],
        ['2.0.0'],
        ['20250101.1.2.3'],
    ];

    /** Bounds of the property tests, with and without a pre-release. */
    private const BOUNDS = [
        '0.0.1', '1', '1.0', '1.0.0', '1.0.0.1', '1.0.1', '1.2.3.4.5', '2', '20250101.1',
        '1.0.0-alpha.2', '1.0.0-beta.1', '1.0.0-beta.2', '1.0.0-rc.1', '1.0.0.1-beta.1', '2.0.0-beta.1', '1.0.0-preview',
    ];

    public static function provideMatches(): \Generator
    {
        // Comparisons against a version number: every build of the number counts as the number
        yield ['1.2.3', '1.2.3', true];
        yield ['1.2.3', '1.2.3.0.0', true];
        yield ['1.2.3', 'v1.2.3', true];
        yield ['1.2.3', '1.2.4', false];
        yield ['1.2.3', '1.2.3.1', false];
        yield ['1.2.3', '1.2.3-beta.1', true];
        yield ['1.2.3', '1.2.3-feature', true];
        yield ['1.2', '1.2.0', true];
        yield ['1.2', '1.2.1', false];
        yield ['=1.2.3', '1.2.3', true];
        yield ['==1.2.3', '1.2.3', true];
        yield ['==1.2.3', '1.2.2', false];
        yield ['!=1.2.3', '1.2.3', false];
        yield ['!=1.2.3', '1.2.3-beta', false];
        yield ['!=1.2.3', '1.2.4', true];
        yield ['<>1.2.3', '1.2.3', false];
        yield ['<>1.2.3', '1.2.4', true];
        yield ['>1.2', '1.2.0.1', true];
        yield ['>1.2', '1.2.0', false];
        yield ['>1.2', '1.2.0-beta', false];
        yield ['>=1.2', '1.2.0-rc1', true];
        yield ['>=1.2', '1.1.99', false];
        yield ['<2', '1.99', true];
        yield ['<2', '2.0.0-beta', false];
        yield ['<2', '2.0.0', false];
        yield ['<=2', '2.0.0', true];
        yield ['<=2', '2.0.0-beta', true];
        yield ['<=2', '2.0.0.1', false];

        // Caret
        yield ['^1.2.3', '1.2.3', true];
        yield ['^1.2.3', '1.9.9', true];
        yield ['^1.2.3', '1.2.2', false];
        yield ['^1.2.3', '2.0.0', false];
        yield ['^1.2.3', '2.0.0-beta', false];
        yield ['^1', '1.9', true];
        yield ['^1', '2', false];
        yield ['^0.3', '0.3.9', true];
        yield ['^0.3', '0.4.0', false];
        yield ['^0.3.2', '0.3.1', false];
        yield ['^0.3.2', '0.3.9', true];
        yield ['^0.3.2', '0.4', false];
        yield ['^0.0.3', '0.0.3.1', true];
        yield ['^0.0.3', '0.0.4', false];
        yield ['^0', '0.9', true];
        yield ['^0', '1.0', false];
        yield ['^0.0', '0.0.9', true];
        yield ['^0.0', '0.1', false];
        yield ['^0.0.0', '0.0.0', true];
        yield ['^0.0.0', '0.0.1', false];
        yield ['^0.0.3.4', '0.0.3.9', true];
        yield ['^0.0.3.4', '0.0.4', false];
        yield ['^1.2.3.4', '1.2.3.3', false];
        yield ['^1.2.3.4', '1.9', true];
        yield ['^01.2', '1.3', true];

        // Tilde
        yield ['~1', '1.9', true];
        yield ['~1', '2.0', false];
        yield ['~1.2', '1.9.9', true];
        yield ['~1.2', '1.1', false];
        yield ['~1.2', '2.0', false];
        yield ['~1.2.3', '1.2.9', true];
        yield ['~1.2.3', '1.3.0', false];
        yield ['~1.2.3', '1.2.2', false];
        yield ['~1.2.3.4', '1.2.3.9', true];
        yield ['~1.2.3.4', '1.2.4', false];
        yield ['~1.2.3.4.5', '1.2.3.4.9', true];
        yield ['~1.2.3.4.5', '1.2.3.5', false];
        yield ['~0.3', '0.9', true];
        yield ['~0.3', '1.0', false];
        yield ['~0', '0.9', true];

        // Wildcards
        yield ['*', '0.0.1', true];
        yield ['*', '20250101.1.2.3', true];
        yield ['x', '1.0', true];
        yield ['*.*', '1.0', true];
        yield ['1.*', '1.0', true];
        yield ['1.*', '1.99.99', true];
        yield ['1.*', '1.0.0-beta', true];
        yield ['1.*', '0.9', false];
        yield ['1.*', '2.0', false];
        yield ['1.x', '1.5', true];
        yield ['1.X', '1.5', true];
        yield ['v1.*', '1.5', true];
        yield ['1.*.*', '1.5', true];
        yield ['1.2.*', '1.2.9', true];
        yield ['1.2.*', '1.3', false];
        yield ['1.2.x', '1.1', false];
        yield ['1.2.3.4.*', '1.2.3.4.7', true];
        yield ['1.2.3.4.*', '1.2.3.5', false];
        yield ['0.*', '0.0.1', true];
        yield ['0.*', '1.0', false];

        // Hyphen ranges
        yield ['1.0 - 2.0', '1.0', true];
        yield ['1.0 - 2.0', '0.9', false];
        yield ['1.0 - 2.0', '2.0.9', true];
        yield ['1.0 - 2.0', '2.1', false];
        yield ['1 - 2', '2.9', true];
        yield ['1 - 2', '3.0', false];
        yield ['1.2.3 - 2.3.4', '2.3.4', true];
        yield ['1.2.3 - 2.3.4', '2.3.4.1', false];
        yield ['1.2.3 - 2.3.4', '1.2.2', false];
        yield ['1.0 - 2.0.0.0', '2.0.1', false];
        yield ['v1.0 - v2.0', '2.0.5', true];
        yield ['1.0  -  2.0', '1.5', true];
        yield ['1.0.0-beta.1 - 2.0', '1.0.0-beta.1', true];
        yield ['1.0.0-beta.2 - 2.0', '1.0.0-beta.1', false];
        yield ['1.0 - 2.0.0-beta.1', '2.0.0-beta.1', true];
        yield ['1.0 - 2.0.0-beta.1', '2.0.0-beta.2', false];
        yield ['1.0 - 2.0-beta.1', '2.0.0-beta.1', true];
        yield ['1.0 - 2.0-beta.1', '2.0.0-beta.2', false];
        yield ['1.0.0-1 - 2.0', '1.0.0', false];

        // OR and AND
        yield ['^1.2 || ^2.0', '1.5', true];
        yield ['^1.2 || ^2.0', '2.5', true];
        yield ['^1.2 || ^2.0', '3.0', false];
        yield ['1.0|2.0', '2.0', true];
        yield ['1.0|2.0', '1.5', false];
        yield ['1.0 | 2.0', '1.0', true];
        yield ['>=1.0 <2.0', '1.5', true];
        yield ['>=1.0 <2.0', '2.0', false];
        yield ['>=1.0  <2.0', '0.9', false];
        yield ['>=1.0,<2.0', '1.5', true];
        yield ['>=1.0, <2.0', '2.0', false];
        yield ['>=1.0 , <2.0', '1.5', true];
        yield ['<2.0 >=1.0', '1.5', true];
        yield ['>= 1.0 < 2.0', '1.5', true];
        yield ['>= 1.0 < 2.0', '2.5', false];
        yield ['>=1.0 <2.0 || >=3.0', '2.5', false];
        yield ['>=1.0 <2.0 || >=3.0', '3.5', true];
        yield ['^1.0 !=1.2.3', '1.2.3', false];
        yield ['^1.0 !=1.2.3', '1.2.4', true];
        yield ['1.0 - 2.0 || 3.*', '3.1', true];

        // A `v` prefix and a space after the operator
        yield ['v1.2.3', '1.2.3', true];
        yield ['>=v1.2', '1.3', true];
        yield ['^ 1.2', '1.3', true];
        yield ['!= 1.2', '1.2', false];

        // Any count of number parts
        yield ['>=1.2.3.4.5', '1.2.3.4.5', true];
        yield ['>=1.2.3.4.5', '1.2.3.4.4', false];
        yield ['>=1.2.3.4.5', '1.2.3.5', true];
        yield ['1.2.3.4.5', '1.2.3.4.5.0', true];
        yield ['<1.2.3.4.5', '1.2.3.4', true];
        yield ['^1.2.3.4.5', '1.9', true];
        yield ['1.0.0.0.0', '1', true];
        yield ['>=1.0', '20250101.1.2.3', true];
        yield ['^20250101.1', '20250101.1.2.3', true];
        yield ['>=1.0', '123456.1.2.3', true];

        // A bound with a pre-release is one release, ordered as in sorting
        yield ['3.5.0-beta.1', '3.5.0-beta.1', true];
        yield ['3.5.0-beta.1', '3.5.0-b1', true];
        yield ['3.5.0-beta.1', '3.5.0.0-beta.1', true];
        yield ['3.5.0-beta.1', '3.5.0-beta.1-linux', false];
        yield ['3.5.0-beta.1', '3.5.0-beta.1.2', false];
        yield ['3.5.0-beta.1', '3.5.0', false];
        yield ['!=3.5.0-beta.1', '3.5.0-beta.1', false];
        yield ['!=3.5.0-beta.1', '3.5.0-beta.1-linux', true];
        yield ['>=3.5.0-beta.1', '3.5.0-alpha.9', false];
        yield ['>=3.5.0-beta.1', '3.5.0-beta.1-linux', true];
        yield ['>3.5.0-RC1', '3.5.0-RC1-linux', true];
        yield ['>3.5.0-RC1', '3.5.0-RC1', false];
        yield ['<3.5.0-RC1', '3.5.0-RC1-linux', false];
        yield ['<3.5.0-RC1', '3.5.0-beta.9', true];
        yield ['<=3.5.0-RC1', '3.5.0-RC1', true];
        yield ['<=3.5.0-RC1', '3.5.0-RC1-linux', false];
        yield ['^3.5.0-beta.1', '3.5.0-beta.2', true];
        yield ['^3.5.0-beta.1', '3.9', true];
        yield ['^3.5.0-beta.1', '4.0.0-beta', false];
        yield ['~3.5.0-rc.1', '3.5.4', true];
        yield ['~3.5.0-rc.1', '3.6.0', false];
        yield ['^2025.1.0-rc.1', '2025.1.0-rc.2', true];
        yield ['^1.2.3.4.5-beta.1', '1.2.3.4.5-beta.2', true];
        yield ['^1.2.3.4.5-beta.1', '1.2.3.4.5-alpha', false];
        yield ['>=1.0.0-beta', '1.0.0-beta.3', true];

        // A numeric tail is a part of the number, in matching as in sorting
        yield ['<3.5.0-preview.3', '3.5.0-1', false];
        yield ['>=3.5.0-preview.3', '3.5.0-1', true];
        yield ['<=1.0.0', '1.0.0-1', false];
        yield ['>1.0.0', '1.0.0-1', true];
        yield ['1.0.0', '1.0.0-1', false];
        yield ['<1.0.0.1', '1.0.0-1', false];
        yield ['1.0.0.1', '1.0.0-1', true];
        yield ['1.0.0-1', '1.0.0-1', true];
        yield ['1.0.0-1', '1.0.0', false];
        yield ['>=1.0.0-1', '1.0.0-2', true];
        yield ['^1.0.0-1', '1.9', true];
        yield ['~1.0.0-1', '1.0.9', true];
        yield ['~1.0.0-1', '1.1', false];

        // Number parts past the integer range
        yield ['^99999999999999999999', '1.0', false];
        yield ['~9223372036854775807', '1.0', false];
        yield ['9223372036854775807.*', '1.0', false];
        yield ['^0.99999999999999999999', '1.0', false];

        // Build metadata does not count
        yield ['1.2.3', '1.2.3+5', true];
        yield ['>1.2.3', '1.2.3+5', false];
        yield ['1.2.3.5', '1.2.3+5', false];
        yield ['1.2.3+5', '1.2.3', true];
        yield ['^1.2.3+5', '1.9', true];
        yield ['~1.2.3+5', '1.3', false];
        yield ['1.0 - 2+5', '2.5', true];
    }

    public static function provideInvalidConstraints(): \Generator
    {
        yield 'empty' => ['', 'Version constraint cannot be empty.'];
        yield 'spaces' => ['  ', 'Version constraint cannot be empty.'];
        yield 'word' => ['abc', 'Invalid base version format: abc.'];
        yield 'branch' => ['dev-master', 'Invalid base version format: dev-master.'];
        yield 'feature suffix' => ['1.2.3-feature', 'Invalid base version format: 1.2.3-feature.'];
        yield 'letter in the number' => ['1.2a.3', 'Invalid base version format: 1.2a.3.'];
        yield 'wildcard inside the number' => ['1.*.2', 'Invalid base version format: 1.*.2.'];
        yield 'operator without a version' => ['>=', 'Invalid base version format: >=.'];
        yield 'reversed operator' => ['=>1.0', 'Unsupported operator `=>` in the version constraint `=>1.0`.'];
        yield 'doubled operator' => ['>>1.0', 'Unsupported operator `>>`'];
        yield 'ruby tilde' => ['~>1.0', 'Unsupported operator `~>`'];
        yield 'doubled caret' => ['^^1.0', 'Unsupported operator `^^`'];
        yield 'operator with a pre-release' => ['=>3.5.0-beta.1', 'Unsupported operator `=>`'];
        yield 'wildcard with an operator' => ['>=1.*', 'A wildcard version takes no operator: `>=1.*`.'];
        yield 'wildcard with a caret' => ['^1.*', 'A wildcard version takes no operator: `^1.*`.'];
        yield 'empty last alternative' => ['1.0 ||', 'Empty alternative in the version constraint `1.0 ||`.'];
        yield 'empty first alternative' => ['|| 1.0', 'Empty alternative'];
        yield 'open hyphen range' => ['1.0 -', 'Invalid base version format: -.'];
        yield 'hyphen range of a wildcard' => ['1.* - 2.0', 'Invalid base version format: -.'];
        yield 'empty last term' => ['1.2,', 'Empty term in the version constraint `1.2,`.'];
        yield 'empty first term' => [',1.2', 'Empty term'];
        yield 'empty middle term' => ['^1.0,,^2', 'Empty term'];
        yield 'space inside an operator' => ['> =1.0', 'Invalid base version format: >.'];
        yield 'feature after a numeric tail' => ['1.0.0-1-feature', 'Invalid base version format: 1.0.0-1-feature.'];
    }

    #[DataProvider('provideMatches')]
    #[Test]
    public function isSatisfiedBy(string $constraint, string $version, bool $expected): void
    {
        $result = Range::fromString($constraint)->isSatisfiedBy(Version::fromVersionString($version));

        Assert::same($result, $expected, "Constraint: {$constraint}, Version: {$version}");
    }

    #[DataProvider('provideInvalidConstraints')]
    #[Test]
    public function rejectsAnInvalidConstraint(string $constraint, string $message): void
    {
        Expect::exception(\InvalidArgumentException::class)->withMessageContaining($message);

        Range::fromString($constraint);
    }

    /**
     * A bound with a pre-release is a version: each operator accepts what {@see Version::compare()} says.
     */
    #[Test]
    public function preReleaseBoundAgreesWithSorting(): void
    {
        foreach (self::BOUNDS as $bound) {
            if (!\str_contains($bound, '-')) {
                continue;
            }

            $boundVersion = Version::fromVersionString($bound);
            foreach (self::OPERATORS as $operator) {
                $range = Range::fromString($operator . $bound);
                foreach (self::versions() as $version) {
                    $expected = Operator::from($operator)->accepts($version->compare($boundVersion));
                    Assert::same(
                        $range->isSatisfiedBy($version),
                        $expected,
                        "Constraint: {$operator}{$bound}, Version: {$version}",
                    );
                }
            }
        }
    }

    /**
     * Every operator accepts an interval of the sorting order: a version between two accepted versions
     * is accepted too, and equal versions are accepted together.
     */
    #[Test]
    public function everyBoundAcceptsAnIntervalOfTheSortingOrder(): void
    {
        $constraints = [];
        foreach (self::BOUNDS as $bound) {
            foreach ([...self::OPERATORS, '^', '~'] as $operator) {
                $constraints[] = $operator . $bound;
            }
        }
        \array_push($constraints, '1.*', '1.0.*', '1.0 - 1.0.0.1', '1 - 2.0.0-beta.1');

        foreach ($constraints as $constraint) {
            $range = Range::fromString($constraint);
            $accepted = [];
            foreach (self::ORDERED_VERSIONS as $row => $equal) {
                $results = \array_map(
                    static fn(string $version): bool => $range->isSatisfiedBy(Version::fromVersionString($version)),
                    $equal,
                );
                Assert::same(\count(\array_unique($results)), 1, "Constraint {$constraint} splits " . \implode(', ', $equal));
                $results[0] and $accepted[] = $row;
            }

            $accepted === [] or \str_starts_with($constraint, '!=') or Assert::same(
                \count($accepted),
                \max($accepted) - \min($accepted) + 1,
                "Constraint {$constraint} accepts a gap",
            );
        }
    }

    #[Test]
    public function orderedVersionsAreSorted(): void
    {
        foreach (self::ORDERED_VERSIONS as $row => $equal) {
            foreach ($equal as $version) {
                Assert::same(Version::fromVersionString($version)->compare(Version::fromVersionString($equal[0])), 0);
                $row === 0 or Assert::same(
                    Version::fromVersionString($version)->compare(Version::fromVersionString(self::ORDERED_VERSIONS[$row - 1][0])),
                    1,
                    "{$version} sorts above " . self::ORDERED_VERSIONS[$row - 1][0],
                );
            }
        }
    }

    /**
     * @return list<Version>
     */
    private static function versions(): array
    {
        return \array_map(Version::fromVersionString(...), \array_merge(...self::ORDERED_VERSIONS));
    }
}
