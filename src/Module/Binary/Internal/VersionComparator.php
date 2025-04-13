<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\Binary\Internal;

/**
 * Compares semantic versions and checks version constraints.
 *
 * @internal
 */
final class VersionComparator
{
    /**
     * Checks if a version satisfies a version constraint.
     *
     * @param string $version Version to check
     * @param string $constraint Version constraint
     * @return bool True if version satisfies constraint
     */
    public function satisfies(string $version, string $constraint): bool
    {
        // Normalize versions by stripping 'v' prefix
        $version = \ltrim($version, 'v');

        // Handle different constraint types
        if (\str_starts_with($constraint, '^')) {
            return $this->satisfiesCaretRange($version, \substr($constraint, 1));
        }

        if (\str_starts_with($constraint, '~')) {
            return $this->satisfiesTildeRange($version, \substr($constraint, 1));
        }

        if (\str_contains($constraint, ' ')) {
            return $this->satisfiesExplicitRange($version, $constraint);
        }

        // Default to exact version comparison for simple constraints
        return $this->compareVersions($version, $constraint) === 0;
    }

    /**
     * Compares two versions and returns comparison result.
     *
     * @param string $versionA First version
     * @param string $versionB Second version
     * @return int -1 if A < B, 0 if A = B, 1 if A > B
     */
    public function compareVersions(string $versionA, string $versionB): int
    {
        // Normalize versions
        $versionA = \ltrim($versionA, 'v');
        $versionB = \ltrim($versionB, 'v');

        // Extract components
        $componentsA = $this->extractVersionComponents($versionA);
        $componentsB = $this->extractVersionComponents($versionB);

        // Compare major, minor, patch
        for ($i = 0; $i < 3; $i++) {
            $a = $componentsA[$i] ?? 0;
            $b = $componentsB[$i] ?? 0;

            if ($a > $b) {
                return 1;
            }

            if ($a < $b) {
                return -1;
            }
        }

        // If we reach here, major.minor.patch are equal
        // Compare pre-release and build metadata
        return $this->comparePreRelease($versionA, $versionB);
    }

    /**
     * Extracts version components from a version string.
     *
     * @param string $version Version string
     * @return array<int, int> Array of major, minor, patch components
     */
    private function extractVersionComponents(string $version): array
    {
        // Remove build metadata and pre-release parts
        $versionOnly = \preg_replace('/[-+].*$/', '', $version);

        // Split into components
        $parts = \explode('.', $versionOnly);

        // Convert to integers and ensure we have 3 parts
        return [
            (int) ($parts[0] ?? 0),
            (int) ($parts[1] ?? 0),
            (int) ($parts[2] ?? 0),
        ];
    }

    /**
     * Compares pre-release versions according to semver rules.
     *
     * @param string $versionA First version
     * @param string $versionB Second version
     * @return int -1 if A < B, 0 if A = B, 1 if A > B
     */
    private function comparePreRelease(string $versionA, string $versionB): int
    {
        // Extract pre-release parts
        \preg_match('/-([^+]+)/', $versionA, $preReleaseA);
        \preg_match('/-([^+]+)/', $versionB, $preReleaseB);

        $hasPreA = isset($preReleaseA[1]);
        $hasPreB = isset($preReleaseB[1]);

        // No pre-release > Has pre-release
        if (!$hasPreA && $hasPreB) {
            return 1;
        }

        if ($hasPreA && !$hasPreB) {
            return -1;
        }

        if (!$hasPreA && !$hasPreB) {
            return 0;
        }

        // Both have pre-release identifiers, compare them
        $identifiersA = \explode('.', $preReleaseA[1]);
        $identifiersB = \explode('.', $preReleaseB[1]);

        $count = \min(\count($identifiersA), \count($identifiersB));

        for ($i = 0; $i < $count; $i++) {
            $a = $identifiersA[$i];
            $b = $identifiersB[$i];

            // Numeric identifiers always have lower precedence than non-numeric
            $aIsNum = \ctype_digit($a);
            $bIsNum = \ctype_digit($b);

            if ($aIsNum && !$bIsNum) {
                return -1;
            }

            if (!$aIsNum && $bIsNum) {
                return 1;
            }

            // If both are numeric or both are non-numeric, compare normally
            if ($aIsNum && $bIsNum) {
                $aVal = (int) $a;
                $bVal = (int) $b;

                if ($aVal > $bVal) {
                    return 1;
                }

                if ($aVal < $bVal) {
                    return -1;
                }
            } else {
                // Non-numeric comparison
                $comparison = \strcmp($a, $b);

                if ($comparison !== 0) {
                    return $comparison > 0 ? 1 : -1;
                }
            }
        }

        // If we've compared all common identifiers and they're equal,
        // the one with more identifiers has lower precedence
        return \count($identifiersA) <=> \count($identifiersB);
    }

    /**
     * Checks if a version satisfies a caret range constraint (^X.Y.Z).
     * Allows changes that don't modify the most significant non-zero digit.
     *
     * @param string $version Version to check
     * @param string $constraint Base version for the constraint (without ^)
     * @return bool True if version satisfies constraint
     */
    private function satisfiesCaretRange(string $version, string $constraint): bool
    {
        $components = $this->extractVersionComponents($constraint);

        // Find the most significant non-zero component
        $significantIndex = 0;
        foreach ($components as $i => $value) {
            if ($value > 0) {
                $significantIndex = $i;
                break;
            }
        }

        // Create lower bound (same as constraint)
        $lowerBound = $constraint;

        // Create upper bound by incrementing the significant digit and zeroing others
        $upperComponents = $components;
        $upperComponents[$significantIndex]++;

        for ($i = $significantIndex + 1; $i < \count($upperComponents); $i++) {
            $upperComponents[$i] = 0;
        }

        $upperBound = \implode('.', $upperComponents);

        // Check if version is in range [lowerBound, upperBound)
        return $this->compareVersions($version, $lowerBound) >= 0 &&
               $this->compareVersions($version, $upperBound) < 0;
    }

    /**
     * Checks if a version satisfies a tilde range constraint (~X.Y.Z).
     * Allows patch-level changes if minor version is specified,
     * minor-level changes if only major version is specified.
     *
     * @param string $version Version to check
     * @param string $constraint Base version for the constraint (without ~)
     * @return bool True if version satisfies constraint
     */
    private function satisfiesTildeRange(string $version, string $constraint): bool
    {
        $components = $this->extractVersionComponents($constraint);
        $versionParts = \explode('.', \preg_replace('/[-+].*$/', '', $constraint));

        // Determine the index to increment based on constraint specificity
        $incrementIndex = \count($versionParts) > 1 ? 1 : 0;

        // Create lower bound (same as constraint)
        $lowerBound = $constraint;

        // Create upper bound by incrementing the appropriate digit and zeroing others
        $upperComponents = $components;
        $upperComponents[$incrementIndex]++;

        for ($i = $incrementIndex + 1; $i < \count($upperComponents); $i++) {
            $upperComponents[$i] = 0;
        }

        $upperBound = \implode('.', $upperComponents);

        // Check if version is in range [lowerBound, upperBound)
        return $this->compareVersions($version, $lowerBound) >= 0 &&
               $this->compareVersions($version, $upperBound) < 0;
    }

    /**
     * Checks if a version satisfies an explicit range constraint (e.g., ">1.0.0 <2.0.0").
     *
     * @param string $version Version to check
     * @param string $constraint Range constraint
     * @return bool True if version satisfies constraint
     */
    private function satisfiesExplicitRange(string $version, string $constraint): bool
    {
        $conditions = \explode(' ', \trim($constraint));

        foreach ($conditions as $condition) {
            if (empty($condition)) {
                continue;
            }

            // Extract the operator and version
            \preg_match('/^([<>=!~^]+)(.*)$/', $condition, $matches);

            if (!isset($matches[1]) || !isset($matches[2])) {
                continue;
            }

            $operator = $matches[1];
            $conditionVersion = $matches[2];

            $comparison = $this->compareVersions($version, $conditionVersion);

            // Check if condition is met
            $satisfied = match ($operator) {
                '>' => $comparison > 0,
                '>=' => $comparison >= 0,
                '<' => $comparison < 0,
                '<=' => $comparison <= 0,
                '=' => $comparison === 0,
                '==' => $comparison === 0,
                '!=' => $comparison !== 0,
                '^' => $this->satisfiesCaretRange($version, $conditionVersion),
                '~' => $this->satisfiesTildeRange($version, $conditionVersion),
                default => false,
            };

            // If any condition is not satisfied, the range is not satisfied
            if (!$satisfied) {
                return false;
            }
        }

        // All conditions were satisfied
        return true;
    }
}
