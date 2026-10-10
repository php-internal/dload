<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\Version;

/**
 * Version range of a constraint in the Composer syntax, matched with DLoad's version ordering.
 *
 * Supports:
 * - Comparisons: `1.2.3`, `=1.2.3`, `==1.2.3`, `!=1.2.3`, `<>1.2.3`, `>1.2`, `>=1.2`, `<2`, `<=2.0`
 * - Caret and tilde ranges: `^1.2.3`, `^0.3`, `~1.2`, `~1.2.3`
 * - Wildcards: `*`, `1.*`, `1.2.x`
 * - Hyphen ranges: `1.0 - 2.0`
 * - AND by a space or a comma, OR by `||` or `|`: `>=1.0 <2.0 || ^3.0`
 * - A `v` prefix, pre-releases and numeric tails in bounds: `v1.2.3`, `^3.5.0-beta.1`, `>=1.0.0-1`
 *
 * Any count of number parts is supported on both sides, and trailing zero parts do not count.
 *
 * ```php
 * Range::fromString('^1.2 || ^2.0')->isSatisfiedBy(Version::fromVersionString('2.3.4')); // true
 * ```
 *
 * @internal
 */
final class Range
{
    /**
     * @param list<list<Comparison>> $groups Alternatives of comparisons that must all hold;
     *        an empty group matches every version.
     */
    private function __construct(
        private readonly array $groups,
    ) {}

    /**
     * @throws \InvalidArgumentException If the constraint syntax is invalid
     */
    public static function fromString(string $constraint): self
    {
        $constraint = \trim($constraint);
        $constraint === '' and throw new \InvalidArgumentException('Version constraint cannot be empty.');

        $groups = [];
        foreach (\preg_split('/\s*\|\|?\s*/', $constraint) ?: [] as $group) {
            $groups[] = self::parseGroup($group, $constraint);
        }

        return new self($groups);
    }

    public function isSatisfiedBy(Version $version): bool
    {
        foreach ($this->groups as $comparisons) {
            foreach ($comparisons as $comparison) {
                if (!$comparison->isSatisfiedBy($version)) {
                    continue 2;
                }
            }

            return true;
        }

        return false;
    }

    /**
     * @return list<Comparison>
     */
    private static function parseGroup(string $group, string $constraint): array
    {
        $group === '' and throw new \InvalidArgumentException("Empty alternative in the version constraint `{$constraint}`.");

        $version = '(v?\d+(?:\.\d+)*(?:\+\d+)?(?:-(?:' . self::preReleasePattern() . '))?)';
        if (\preg_match("/^{$version}\\s+-\\s+{$version}$/i", $group, $matches) === 1) {
            return self::parseHyphenRange($matches[1], $matches[2]);
        }

        # Composer allows a space after an operator: `>= 1.0` is one term, not two, while `> =1.0` is no term
        $group = (string) \preg_replace('/(?<=^|[\s,])([<>=!~^]+)\s+(?=[v\d])/i', '$1', $group);

        $comparisons = [];
        foreach (\preg_split('/\s*,\s*|\s+/', $group) ?: [] as $term) {
            $term === '' and throw new \InvalidArgumentException("Empty term in the version constraint `{$constraint}`.");
            $comparisons = [...$comparisons, ...self::parseTerm($term)];
        }

        return $comparisons;
    }

    /**
     * @return list<Comparison>
     */
    private static function parseTerm(string $term): array
    {
        \preg_match('/^([<>=!~^]*)(.*)$/s', $term, $matches);
        [, $operator, $version] = $matches;

        if (\preg_match('/^v?(?:\d+(?:\.\d+)*\.)?[x*](?:\.[x*])*$/i', $version) === 1) {
            $operator === '' or throw new \InvalidArgumentException(
                "A wildcard version takes no operator: `{$term}`.",
            );

            return self::parseWildcard($version);
        }

        [$number, $preRelease] = self::parseVersion($version) ?? throw new \InvalidArgumentException(
            "Invalid base version format: {$term}.",
        );

        return match ($operator) {
            '^' => [
                Comparison::create(Operator::GreaterOrEqual, $number, $preRelease),
                Comparison::create(Operator::Less, self::increment($number, self::caretPosition($number))),
            ],
            '~' => [
                Comparison::create(Operator::GreaterOrEqual, $number, $preRelease),
                Comparison::create(Operator::Less, self::increment($number, \max(1, self::countParts($number) - 1))),
            ],
            default => [
                Comparison::create(
                    Operator::fromString($operator) ?? throw new \InvalidArgumentException(
                        "Unsupported operator `{$operator}` in the version constraint `{$term}`.",
                    ),
                    $number,
                    $preRelease,
                ),
            ],
        };
    }

    /**
     * `1.2.*` is `>=1.2 <1.3`; `*` matches every version.
     *
     * @return list<Comparison>
     */
    private static function parseWildcard(string $version): array
    {
        \preg_match('/^v?((?:\d+\.)*)/i', $version, $matches);
        $number = \rtrim($matches[1], '.');
        if ($number === '') {
            return [];
        }

        return [
            Comparison::create(Operator::GreaterOrEqual, $number),
            Comparison::create(Operator::Less, self::increment($number, self::countParts($number))),
        ];
    }

    /**
     * A partial upper version covers all its builds, as in Composer: `1.0 - 2` is `>=1.0 <3`,
     * while `1.0 - 2.0.0` is `>=1.0 <=2.0.0`.
     *
     * @return list<Comparison>
     */
    private static function parseHyphenRange(string $from, string $to): array
    {
        $lower = self::parseVersion($from);
        $upper = self::parseVersion($to);
        \assert($lower !== null && $upper !== null);

        $parts = self::countParts($upper[0]);
        return [
            Comparison::create(Operator::GreaterOrEqual, ...$lower),
            $parts >= 3 || $upper[1] !== ''
                ? Comparison::create(Operator::LessOrEqual, ...$upper)
                : Comparison::create(Operator::Less, self::increment($upper[0], $parts)),
        ];
    }

    /**
     * @return array{non-empty-string, string}|null Version number and pre-release, or null if the text is not a version.
     */
    private static function parseVersion(string $version): ?array
    {
        # Build metadata does not count, so the number goes without it
        $pattern = '/^v?(\d+(?:\.\d+)*)(?:\+\d+)?(?:-(' . self::preReleasePattern() . '))?$/i';
        if (\preg_match($pattern, $version, $matches) !== 1) {
            return null;
        }

        /** @var non-empty-string $number */
        $number = $matches[1];
        return [$number, $matches[2] ?? ''];
    }

    /**
     * The caret keeps the first non-zero of the three leading parts: `^1.2` is `<2`, `^0.3` is `<0.4`,
     * `^0.0.3` is `<0.0.4`; `^0` is `<1` and `^0.0` is `<0.1`.
     *
     * @return int<1, 3>
     */
    private static function caretPosition(string $number): int
    {
        $parts = self::parts($number);
        return match (true) {
            (int) $parts[0] !== 0 || \count($parts) === 1 => 1,
            (int) $parts[1] !== 0 || \count($parts) === 2 => 2,
            default => 3,
        };
    }

    /**
     * Cuts the number after the given part and increments that part: `1.2.3` at 2 is `1.3`.
     * Parts are digit strings of any length, so `^99999999999999999999` does not overflow an integer.
     *
     * @param int<1, max> $position Not past the last part of the number
     * @return non-empty-string
     */
    private static function increment(string $number, int $position): string
    {
        $parts = \array_map(
            static fn(string $part): string => \ltrim($part, '0') ?: '0',
            \array_slice(self::parts($number), 0, $position),
        );

        $last = $parts[$position - 1];
        $nines = \strlen($last) - \strlen(\rtrim($last, '9'));
        $parts[$position - 1] = $nines === \strlen($last)
            ? '1' . \str_repeat('0', $nines)
            : \substr($last, 0, -$nines - 1) . ((int) $last[-$nines - 1] + 1) . \str_repeat('0', $nines);

        return \implode('.', $parts);
    }

    /**
     * @return int<1, max>
     */
    private static function countParts(string $number): int
    {
        return \count(self::parts($number));
    }

    /**
     * @return non-empty-list<string>
     */
    private static function parts(string $number): array
    {
        return \explode('.', $number);
    }

    /**
     * Regular expression of a bound pre-release after its hyphen: a stability keyword with an optional number,
     * like `beta.1`, or a numeric tail, like `1` of `1.0.0-1`, that orders as a part of the number.
     *
     * @return non-empty-string
     */
    private static function preReleasePattern(): string
    {
        return '(?:' . PreRelease::keywordPattern() . ')(?:[._-]?\d+)?|\d+(?:\.\d+)*';
    }
}
