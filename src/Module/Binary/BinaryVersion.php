<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\Binary;

use Internal\DLoad\Module\Version\Version;

/**
 * Represents a binary version parsed from command output.
 *
 * @internal
 */
final class BinaryVersion extends Version
{
    /**
     * Pattern to extract semantic version (X.Y.Z) from text.
     */
    private const OUTPUT_VERSION_PATTERN = '/(?:version:?\s*)?(?:v(?:er(?:sion)?)?\.?\s*)?' . parent::VERSION_SEMVER_PATTERN . '/i';

    /**
     * Resolves the version from binary command output.
     *
     * @param non-empty-string $output Output from binary execution
     * @return BinaryVersion Extracted version
     */
    public static function fromBinaryOutput(string $output): static
    {
        // Try to extract version using semantic version pattern
        $version = \preg_match(self::OUTPUT_VERSION_PATTERN, $output, $matches)
            ? $matches[1] . $matches[2]
            : self::extractVersionWithFallbacks($output);

        if ($version === null) {
            return self::empty();
        }

        \assert($version !== '');

        return self::fromVersionString($version);
    }

    /**
     * Attempts to extract version using various fallback patterns.
     *
     * @param string $output Output from binary execution
     * @return non-empty-string|null Extracted version or null if no version found
     */
    private static function extractVersionWithFallbacks(string $output): ?string
    {
        // Fallback pattern for partial semver (e.g., "2.0")
        if (\preg_match('/version:?\s*(\d+\.\d+)/i', $output, $matches)) {
            \assert($matches[1] !== '');
            return $matches[1];
        }

        // Fallback pattern for simple digits-only version (e.g., "2", "15")
        if (\preg_match('/version:?\s*(\d+)/i', $output, $matches)) {
            \assert($matches[1] !== '');
            return $matches[1];
        }

        // Fallback pattern for 'libprotoc 30.2'
        if (\preg_match('/^[\w-]+\s+v?(\d+(?:\\.\d+){0,2})/i', $output, $matches)) {
            \assert($matches[1] !== '');
            return $matches[1];
        }

        // No version could be extracted
        return null;
    }
}
