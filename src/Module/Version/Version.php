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
    protected const VERSION_SEMVER_NUMBER_PATTERN = 'v?(\d+\.\d+\.\d+(?:\.\d+)*)';

    /** A suffix starting with a letter may follow the number right away: `2.0.0rc1`, `1.2.3_beta2`. */
    protected const VERSION_FALLBACK_PATTERN = 'v?(\d+(?:\.\d+)*)((?:[-+.]|(?=[a-z_]))[\w.-]+)?';

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
     * Regular expression of SemVer build metadata: a plus and dot-separated identifiers of letters, digits
     * and hyphens, like `+build.5` or `+5-beta.1`.
     *
     * @return non-empty-string
     */
    public static function buildPattern(): string
    {
        return '\+[0-9a-z-]+(?:\.[0-9a-z-]+)*';
    }

    /**
     * Parses a version string into its components.
     *
     * Build metadata at the end, see {@see buildPattern()}, is dropped as in SemVer: it means nothing for
     * the order, the stability or the suffix, so `1.2.3-linux+build.5` is `1.2.3-linux`. It stays only
     * in {@see $string}. A plus that starts no metadata, as in `1.2.3+linux_x64`, starts a suffix.
     *
     * @param non-empty-string $string Version string to parse
     */
    public static function fromVersionString(string $string): static
    {
        $version = (string) \preg_replace(
            '/' . self::buildPattern() . '(?=' . self::VERSION_HASH_SUFFIX_PATTERN . '$)/i',
            '',
            $string,
            1,
        );

        // Parse the version number
        \preg_match(
            '/^' . self::VERSION_FALLBACK_PATTERN . self::VERSION_HASH_SUFFIX_PATTERN . '$/i',
            $version,
            $parts,
        ) or throw new \InvalidArgumentException(
            "Failed version string: {$string}.",
        );

        $number = $parts[1];
        \assert($number !== '');

        $suffix = $parts[2] ?? '';
        // A lone letter glued to the number is a patch letter, like OpenSSL's `1.1.1b`, not a pre-release
        $preRelease = $suffix === '' || \preg_match('/^[a-z]$/i', $suffix) === 1
            ? null
            : self::preReleaseFromSuffix($suffix);
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
     * then by the rest of the suffix, see {@see compareTail()}: `1.0.0-RC1 < 1.0.0-RC1-priority.0`.
     * A version without a number comes first.
     *
     * @return int<-1, 1>
     */
    public function compare(self $other): int
    {
        $byNumber = $this->compareNumber($other);
        if ($byNumber !== 0 || $this->preRelease === null || $other->preRelease === null) {
            return $byNumber;
        }

        $byPreRelease = $this->preRelease->compare($other->preRelease);
        if ($byPreRelease !== 0) {
            return $byPreRelease;
        }

        return self::compareTail($this->suffix, $other->suffix);
    }

    /**
     * Orders versions by the version number only, the first step of {@see compare()}:
     * `1.0.0-beta` and `1.0.0` are equal, `1.0.0-1` is above both.
     *
     * @return int<-1, 1>
     */
    public function compareNumber(self $other): int
    {
        $parts = $this->comparableParts();
        $otherParts = $other->comparableParts();
        foreach ($parts as $i => $part) {
            if (!isset($otherParts[$i])) {
                return 1;
            }

            $byPart = self::compareDigits($part, $otherParts[$i]);
            if ($byPart !== 0) {
                return $byPart;
            }
        }

        return \count($parts) <=> \count($otherParts);
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
        $reg = '[._-]?' . PreRelease::wordPattern();

        /** @var array<0|1, non-empty-string> $parts */
        $parts = [];

        \preg_match('#' . $reg . '$#i', $input, $match) === 1 and $parts[0] = $match[0];
        \preg_match('#^' . $reg . '#i', $input, $match) === 1 and $parts[1] = $match[0];

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
     * Orders the suffixes left after the pre-release token by token, a token being a run of digits or of letters.
     * A suffix that ends where the other goes on comes first: `RC1 < RC1-priority.0`. A word comes before
     * a number, as a word suffix sorts below the final release and a numeric one above it:
     * `beta.1-linux < beta.1.2`. Numbers compare by value, words alphabetically. Stability keywords mean
     * nothing here, so `arm64` is no alpha, and neither do case, separators and leading zeros:
     * `Linux_x64.01` equals `linux-x64.1`.
     *
     * @return int<-1, 1>
     */
    private static function compareTail(?string $suffix, ?string $other): int
    {
        $tokens = self::tokens($suffix);
        $otherTokens = self::tokens($other);
        foreach ($tokens as $i => $token) {
            if (!isset($otherTokens[$i])) {
                return 1;
            }

            $otherToken = $otherTokens[$i];
            $isNumber = \ctype_digit($token);
            $byToken = match (true) {
                $isNumber !== \ctype_digit($otherToken) => $isNumber ? 1 : -1,
                $isNumber => self::compareDigits($token, $otherToken),
                default => \strcmp($token, $otherToken) <=> 0,
            };
            if ($byToken !== 0) {
                return $byToken;
            }
        }

        return \count($tokens) <=> \count($otherTokens);
    }

    /**
     * Compares digit strings of any length without leading zeros: as integers, long ones would overflow.
     *
     * @return int<-1, 1>
     */
    private static function compareDigits(string $digits, string $other): int
    {
        return (\strlen($digits) <=> \strlen($other)) ?: \strcmp($digits, $other) <=> 0;
    }

    /**
     * Words in lower case and numbers without leading zeros, like `linux`, `x`, `64` and `1` of `Linux_x64.01`.
     *
     * @return list<string>
     */
    private static function tokens(?string $suffix): array
    {
        \preg_match_all('/\d+|[a-z]+/i', (string) $suffix, $matches);

        return \array_map(
            static fn(string $token): string => \ctype_digit($token) ? (\ltrim($token, '0') ?: '0') : \strtolower($token),
            $matches[0],
        );
    }

    /**
     * A numeric suffix without a stability keyword orders as a part of the number: `1.0.0-1 > 1.0.0`.
     * With a keyword it does not: the `.2` of `2.0.0-beta.1.2` belongs to the pre-release.
     * Leading zeros and trailing zero parts do not count: `1.2.3`, `01.2.3.0` and `1.2.3.0.0` are one number.
     *
     * @return list<string> Parts without leading zeros, empty for a version without a number
     */
    private function comparableParts(): array
    {
        if ($this->number === null) {
            return [];
        }

        $number = $this->number;
        if ($this->withoutKeyword && $this->suffix !== null && \preg_match('/^\d+(?:\.\d+)*$/', $this->suffix) === 1) {
            $number .= '.' . $this->suffix;
        }

        $parts = \array_map(static fn(string $part): string => \ltrim($part, '0') ?: '0', \explode('.', $number));
        while (\count($parts) > 1 && \end($parts) === '0') {
            \array_pop($parts);
        }

        return $parts;
    }
}
