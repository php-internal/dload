<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\Binary\Internal;

use Internal\DLoad\Module\Common\Stability;

/**
 * Resolves version information from binary command output.
 *
 * @internal
 */
final class VersionResolver
{
    /**
     * Pattern to extract semantic version (X.Y.Z) from text.
     */
    private const VERSION_PATTERN = '/(?:version:?\s*)?(?:v(?:er(?:sion)?)?\.?\s*)?(\d+\.\d+\.\d+(?:[-+][\w.]+)?)/i';

    /**
     * Resolves the version from binary command output.
     *
     * @param non-empty-string $output Output from binary execution
     * @return VersionString Extracted version
     */
    public function resolveVersion(string $output): VersionString
    {
        // Try to extract version using semantic version pattern
        $version = \preg_match(self::VERSION_PATTERN, $output, $matches)
            ? $matches[1]
            : $this->extractVersionWithFallbacks($output);

        // Cut the suffix if it exists
        $suffix = null;
        if ($version !== null and false !== $pos = \strpos($output, $version)) {
            $suffix = \trim(\substr($output, $pos + \strlen($version)));
            $suffix = $suffix === '' ? null : $suffix;
        }

        \assert($version !== '');

        // Try fallback patterns if standard pattern fails
        return new VersionString(
            origin: $output,
            version: $version,
            suffix: $suffix,
            stability: Stability::fromReleaseString($output, Stability::Stable),
        );
    }

    /**
     * Attempts to extract version using various fallback patterns.
     *
     * @param string $output Output from binary execution
     * @return non-empty-string|null Extracted version or null if no version found
     */
    private function extractVersionWithFallbacks(string $output): ?string
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
