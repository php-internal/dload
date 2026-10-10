<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\Version;

use Internal\DLoad\Module\Common\Stability;

/**
 * Contains --version output from a binary with parsed parts.
 *
 * @internal
 */
class Version implements \Stringable
{
    protected const VERSION_SEMVER_NUMBER_PATTERN = 'v?(\d+\.\d+\.\d+(?:\.\d+)*(?:\+\d+)?)';

    /** A suffix starting with a letter may follow the number right away: `2.0.0rc1`, `1.2.3_beta2`. */
    protected const VERSION_FALLBACK_PATTERN = 'v?(\d+(?:\.\d+(?:\.\d+(?:\.\d+)*(?:\+\d+)?)?)?)((?:[-+.]|(?=[a-z_]))[\w.-]+)?';

    protected const VERSION_HASH_SUFFIX_PATTERN = '(?:#([a-f0-9]{6,40}))?';

    /**
     * @param string $string Source of the version string (e.g., 1.2.3-beta-feature)
     * @param null|non-empty-string $number Parsed version number
     * @param null|non-empty-string $suffix Feature suffix: the suffix without the stability part
     * @param null|PreRelease $preRelease Stability with its number, e.g. `beta.1` of `1.2.3-beta.1`;
     *        numbered 0 when the version carries none. Null only for an empty version.
     * @param bool $withoutKeyword The suffix has no stability keyword, like `1` of `1.0.0-1`.
     */
    final protected function __construct(
        public readonly string $string,
        public readonly ?string $number = null,
        public readonly ?string $suffix = null,
        public readonly ?Stability $stability = null,
        public readonly ?string $hash = null,
        public readonly ?PreRelease $preRelease = null,
        private readonly bool $withoutKeyword = false,
    ) {}

    /**
     * Parses a version string into its components.
     *
     * @param non-empty-string $string Version string to parse
     */
    public static function fromVersionString(string $string): static
    {
        // Parse the version number
        \preg_match(
            '/^' . self::VERSION_FALLBACK_PATTERN . self::VERSION_HASH_SUFFIX_PATTERN . '$/i',
            $string,
            $parts,
        ) or throw new \InvalidArgumentException(
            "Failed version string: {$string}.",
        );

        $number = $parts[1];
        \assert($number !== '');

        $suffix = $parts[2] ?? '';
        $preRelease = $suffix === '' ? null : self::preReleaseFromSuffix($suffix);
        $withoutKeyword = $preRelease === null;

        $suffix = \trim($suffix, '-_.+');
        $suffix === '' and $suffix = null;

        $stability = $preRelease?->stability ?? ($suffix === null ? Stability::Stable : Stability::Preview);
        $preRelease ??= new PreRelease($stability);

        $hash = $parts[3] ?? null;
        $hash === '' and $hash = null;

        return new static($string, $number, $suffix, $stability, $hash, $preRelease, $withoutKeyword);
    }

    public static function empty(): static
    {
        return new static('');
    }

    /**
     * Orders versions by the version number, then by the pre-release: `1.0.0-beta.2 < 1.0.0-RC1 < 1.0.0`,
     * then by the rest of the suffix, a version without one first:
     * `1.0.0-RC1 < 1.0.0-RC1-priority.0 < 1.0.0-RC1-priority.1`.
     * A version without a number comes first.
     *
     * @return int<-1, 1>
     */
    public function compare(self $other): int
    {
        $byNumber = \version_compare($this->comparableNumber(), $other->comparableNumber());
        if ($byNumber !== 0 || $this->preRelease === null || $other->preRelease === null) {
            return $byNumber;
        }

        $byPreRelease = $this->preRelease->compare($other->preRelease);

        return $byPreRelease !== 0 ? $byPreRelease : \version_compare((string) $this->suffix, (string) $other->suffix);
    }

    public function __toString(): string
    {
        return $this->string;
    }

    /**
     * Cuts the stability part off the version suffix.
     *
     * @param non-empty-string $input Version suffix; the stability part is removed from it
     * @param-out string $input
     * @return null|PreRelease Stability with its number, or null if the suffix has no stability part
     */
    private static function preReleaseFromSuffix(string &$input): ?PreRelease
    {
        $reg = '[._-]?(?:(' . PreRelease::keywordPattern() . ')([._-]?\d+)?)?';

        /** @var list<non-empty-string> $parts */
        $parts = [];

        \preg_match(('#' . $reg . '$#i'), $input, $match);
        isset($match[1]) and $parts[0] = $match[0];

        \preg_match(('#^' . $reg . '#i'), $input, $match);
        isset($match[1]) and $parts[1] = $match[0];

        foreach ($parts as $k => $fullPart) {
            $preRelease = PreRelease::fromString(\ltrim($fullPart, '._-'));
            if ($preRelease !== null) {
                $input = $k === 1
                    ? \substr($input, \strlen($fullPart))
                    : \substr($input, 0, -\strlen($fullPart));

                return $preRelease;
            }
        }

        return null;
    }

    /**
     * A numeric suffix without a stability keyword orders as a part of the number: `1.0.0-1 > 1.0.0`.
     * With a keyword it does not: the `.2` of `2.0.0-beta.1.2` belongs to the pre-release.
     * Trailing zero parts do not count: `1.2.3`, `1.2.3.0` and `1.2.3.0.0` are one number.
     */
    private function comparableNumber(): string
    {
        $number = (string) $this->number;
        if ($this->withoutKeyword && $this->suffix !== null && \preg_match('/^\d+(?:\.\d+)*$/', $this->suffix) === 1) {
            $number .= '.' . $this->suffix;
        }

        return (string) \preg_replace('/(?:\.0+)+$/', '', $number);
    }
}
