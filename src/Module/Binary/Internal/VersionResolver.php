<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\Binary\Internal;

/**
 * Resolves version information from binary command output.
 */
final class VersionResolver
{
    /**
     * Pattern to extract semantic version (X.Y.Z) from text.
     */
    private const VERSION_PATTERN = '/(?:version:?\s*)?(?:v(?:er(?:sion)?)?\.?\s*)?(\d+\.\d+\.\d+(?:[-+][\w\.]+)?)/i';

    /**
     * Resolves the version from binary command output.
     *
     * @param string $output Output from binary execution
     * @return string|null Extracted version or null if no version found
     */
    public function resolveVersion(string $output): ?string
    {
        // Try to extract version using semantic version pattern
        if (\preg_match(self::VERSION_PATTERN, $output, $matches)) {
            return $matches[1];
        }

        // Try fallback patterns if standard pattern fails
        return $this->extractVersionWithFallbacks($output);
    }

    /**
     * Attempts to extract version using various fallback patterns.
     *
     * @param string $output Output from binary execution
     * @return string|null Extracted version or null if no version found
     */
    private function extractVersionWithFallbacks(string $output): ?string
    {
        // Fallback pattern for simple digits-only version (e.g., "2", "15")
        if (\preg_match('/version:?\s*(\d+)/i', $output, $matches)) {
            return $matches[1];
        }

        // Fallback pattern for partial semver (e.g., "2.0")
        if (\preg_match('/version:?\s*(\d+\.\d+)/i', $output, $matches)) {
            return $matches[1];
        }

        // No version could be extracted
        return null;
    }
}
