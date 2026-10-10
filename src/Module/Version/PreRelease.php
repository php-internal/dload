<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\Version;

use Internal\DLoad\Module\Common\Stability;

/**
 * Stability level of a version together with its number, e.g. `beta.1` or `RC2`.
 *
 * Orders versions that share a version number: by the stability weight first, then by the number,
 * so `alpha.3 < beta.1 < beta.2 < RC1 < stable`. Spelling does not matter: `rc.1`, `RC1` and
 * `rc-1` are equal.
 *
 * ```php
 * PreRelease::fromString('beta.2')->compare(PreRelease::fromString('RC1')); // -1
 * ```
 *
 * @internal
 */
final class PreRelease implements \Stringable
{
    /**
     * @param int<0, max> $number Zero when the stability carries no number, like `beta`.
     */
    public function __construct(
        public readonly Stability $stability,
        public readonly int $number = 0,
    ) {}

    /**
     * Parses a stability keyword with an optional number: `beta.1`, `RC2`, `rc-3`, `b4`, `nightly20250503`.
     *
     * @return self|null Null when the text is not a stability keyword.
     */
    public static function fromString(string $value): ?self
    {
        if (\preg_match('/^([a-z]+)[._-]?(\d*)$/i', $value, $matches) !== 1) {
            return null;
        }

        $stability = self::stability($matches[1]);
        if ($stability === null) {
            return null;
        }

        /** @var int<0, max> $number */
        $number = (int) $matches[2];

        return new self($stability, $number);
    }

    /**
     * Regular expression alternation of every keyword {@see stability()} accepts.
     *
     * @return non-empty-string
     */
    public static function keywordPattern(): string
    {
        $keywords = [...\array_column(Stability::cases(), 'value'), 'a', 'b'];
        // Longer keywords first, so `preview` is not read as `pre` followed by garbage
        \usort($keywords, static fn(string $a, string $b): int => \strlen($b) <=> \strlen($a));

        return \implode('|', \array_map(static fn(string $keyword): string => \preg_quote($keyword, '/'), $keywords));
    }

    /**
     * Regular expression of a stability keyword with an optional number, read as a whole word: letters that
     * run together are one word, so `a1`, `beta.2` and the `rc1` of `2.0.0rc1` or `rc1_linux` are keywords,
     * while the `a` of `arm64` or `ga` and the `b` of `build.5` are not.
     *
     * @return non-empty-string
     */
    public static function wordPattern(): string
    {
        return '(?<![a-z])(?:' . self::keywordPattern() . ')(?:[._-]?\d+)?(?![a-z\d])';
    }

    /**
     * @return int<-1, 1> Negative when this pre-release is less stable or older than the other one.
     */
    public function compare(self $other): int
    {
        return [$this->stability->getWeight(), $this->number] <=> [$other->stability->getWeight(), $other->number];
    }

    public function equals(self $other): bool
    {
        return $this->compare($other) === 0;
    }

    public function __toString(): string
    {
        return $this->number === 0 ? $this->stability->value : $this->stability->value . '.' . $this->number;
    }

    /**
     * Resolves a stability keyword, including the `a` and `b` abbreviations of alpha and beta.
     */
    private static function stability(string $keyword): ?Stability
    {
        return Stability::fromString($keyword) ?? match (\strtolower($keyword)) {
            'a' => Stability::Alpha,
            'b' => Stability::Beta,
            default => null,
        };
    }
}
