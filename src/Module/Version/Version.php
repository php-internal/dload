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
    protected const VERSION_FALLBACK_PATTERN = 'v?(\d+(?:\.\d+(?:\.\d+(?:\+\d+)?)?)?)([-+][\w.-]+)?';

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
    ) {}

    /**
     * Parses a version string into its components.
     *
     * @param non-empty-string $string Version string to parse
     */
    public static function fromVersionString(string $string): static
    {
        // Parse the version number
        \preg_match('/^' . self::VERSION_FALLBACK_PATTERN . '$/i', $string, $parts) or throw new \InvalidArgumentException(
            "Failed version string: {$string}.",
        );

        $number = $parts[1];
        \assert($number !== '');

        $suffix = \trim($parts[2] ?? '', '+-');
        $stability = null;
        if ($suffix !== '') {
            // Check if the suffix has a stability keyword
            $parts = \explode('-', $suffix);

            // Check only the first and the last parts of the suffix
            $stability = Stability::fromString($parts[0]);
            if ($stability === null) {
                $stability = Stability::fromString(\end($parts));
                $stability === null or \array_pop($parts);
            } else {
                \array_shift($parts);
            }

            $suffix = \trim(\implode('-', $parts), '-');
        }

        $suffix === '' and $suffix = null;
        $stability ??= Stability::Stable;

        return new static($string, $number, $suffix, $stability);
    }

    public static function empty(): static
    {
        return new static('');
    }

    public function __toString(): string
    {
        return (string) $this->number;
    }
}
