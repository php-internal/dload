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
    protected const VERSION_SEMVER_PATTERN = 'v?(\d+\.\d+\.\d+(?:\+\d+)?)([-+][\w.-]+)?';
    protected const VERSION_FALLBACK_PATTERN = 'v?(\d+(?:\.\d+(?:\.\d+(?:\+\d+)?)?)?)([-+.][\w.-]+)?';
    protected const VERSION_HASH_SUFFIX_PATTERN = '(?:#([a-f0-9]{6,40}))?';

    /**
     * @param string $string Source of the version string (e.g., 1.2.3-beta-feature)
     * @param null|non-empty-string $number Parsed version number
     * @param null|non-empty-string $suffix  Stability and feature suffix
     */
    final protected function __construct(
        public readonly string $string,
        public readonly ?string $number = null,
        public readonly ?string $suffix = null,
        public readonly ?Stability $stability = null,
        public readonly ?string $hash = null,
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
        $stability = null;
        if ($suffix !== '') {
            $stability = self::stabilityFromSuffix($suffix);
        }

        $suffix = \trim($suffix, '-_.+');
        $suffix === '' and $suffix = null;

        $stability ??= ($suffix === null ? Stability::Stable : Stability::Dev);

        $hash = $parts[3] ?? null;
        $hash === '' and $hash = null;

        return new static($string, $number, $suffix, $stability, $hash);
    }

    public static function empty(): static
    {
        return new static('');
    }

    public function __toString(): string
    {
        return $this->string;
    }

    /**
     * Extracts the stability from the version suffix.
     *
     * @param non-empty-string $input Version string with suffix
     * @return null|Stability Stability level or null if not found
     */
    private static function stabilityFromSuffix(string &$input): ?Stability
    {
        if (\str_starts_with('x-dev', \strtolower($input))) {
            $input = \substr($input, \strlen('x-dev'));
            return Stability::Dev;
        }

        // if (\preg_match('{^dev[-_.]}', $input) || \preg_match('{[-_.]dev$}', $input)) {
        //     return Stability::Dev;
        // }

        $mods = \implode('|', \array_column(Stability::cases(), 'value')) . '|b|a';
        $reg = "[._-]?(?:($mods)([.-]?\d+)?)?";

        /** @var list<array{non-empty-string, non-empty-string}> $parts */
        $parts = [];

        \preg_match(('#' . $reg . '$#i'), $input, $match);
        isset($match[1]) and $parts[0] = [$match[1], $match[0]];

        \preg_match(('#^' . $reg . '#i'), $input, $match);
        isset($match[1]) and $parts[1] = [$match[1], $match[0]];

        if ($parts === []) {
            return null;
        }

        foreach ($parts as $k => [$part, $fullPart]) {
            $isPrefix = $k === 1;
            $part = \strtolower($part);
            $stability = Stability::fromString($part) ?? match ($part) {
                'a' => Stability::Alpha,
                'b' => Stability::Beta,
                default => null,
            };
            if ($stability !== null) {
                $input = $isPrefix
                    ? \substr($input, \strlen($fullPart))
                    : \substr($input, 0, -\strlen($fullPart));

                return $stability;
            }
        }

        return null;
    }
}
