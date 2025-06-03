<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\Version;

use Composer\Semver\Semver;
use Internal\DLoad\Module\Common\Stability;

/**
 * Version constraint DTO for parsing and handling complex version requirements.
 *
 * Encapsulates version constraint logic supporting:
 * - Feature suffixes: ^2.12.0-feature, ~1.20.0-hotfix, ^1.0.0-my-feature
 * - Stability constraints with two equivalent syntaxes:
 *   * Explicit: ^2.12.0@beta, ~1.20.0@stable
 *   * Implicit: ^2.12.0-beta, ~1.20.0-stable
 * - Combined constraints: ^2.12.0-feature@beta
 *
 * Stability keywords (from Stability enum) as suffixes are automatically
 * converted to stability constraints.
 *
 * @internal
 */
final class Constraint implements \Stringable
{
    /** @var non-empty-string $versionConstraint Base version constraint (e.g. "^2.12.0") */
    public readonly string $versionConstraint;

    /**
     * @var non-empty-string|null $featureSuffix Optional feature suffix (e.g. "feature", "my-feature")
     *      If null, no feature suffix is specified.
     */
    public readonly ?string $featureSuffix;

    /** @var Stability $minimumStability Minimum stability level for this constraint */
    public readonly Stability $minimumStability;

    /**
     * @param non-empty-string $origin Original constraint string used for parsing.
     */
    private function __construct(
        private string $origin,
    ) {
        if ($origin === '') {
            throw new \InvalidArgumentException('Version constraint cannot be empty.');
        }

        // Extract explicit stability constraint (@stability) first
        $stability = null;
        if (\str_contains($origin, '@')) {
            /** @psalm-suppress PossiblyUndefinedArrayOffset */
            [$origin, $stabilityPart] = \explode('@', $origin, 2);

            $stability = Stability::fromString($stabilityPart) ?? throw new \InvalidArgumentException(
                "Invalid stability level: @{$stabilityPart}.",
            );
        }

        [$version, $suffix] = \explode('-', $origin, 2) + [1 => ''];
        if ($suffix !== '') {
            // Check if suffix is a stability keyword (only if no explicit stability provided)
            if ($stability === null) {
                // Check only the first and the last parts of the suffix
                $parts = \explode('-', $suffix);
                $stability = Stability::fromString($parts[0]);
                if ($stability === null) {
                    $stability = Stability::fromString(\end($parts));
                    $stability === null or \array_pop($parts);
                } else {
                    \array_shift($parts);
                }

                $suffix = \implode('-', $parts);
            }

            $suffix = \trim($suffix, '-');

            // Validate feature suffix format - allow hyphens for multi-word suffixes
            if (!\preg_match('/^[a-zA-Z0-9-.]*$/', $suffix)) {
                throw new \InvalidArgumentException("Invalid feature suffix format: {$suffix}.");
            }
        }

        // Validate base version format for non-empty suffixes
        $suffix === '' and !\preg_match('/^[~^>=<]*\d+(\.\d+)*/', $version) and throw new \InvalidArgumentException(
            "Invalid base version format: {$version}.",
        );
        $suffix === '' and $suffix = null;

        // Determine final stability (explicit takes precedence over implicit)
        $stability ??= $suffix === null ? Stability::Stable : Stability::Preview;

        $version === '' and throw new \InvalidArgumentException('Base version cannot be empty.');

        $this->versionConstraint = $version;
        $this->featureSuffix = $suffix;
        $this->minimumStability = $stability;
    }

    /**
     * Parse version constraint string into DTO.
     *
     * Handles both @stability and -stability syntax equivalence.
     * Auto-converts stability keywords in suffixes to stability constraints.
     *
     * Examples:
     * - "^2.12.0" -> baseVersion: "^2.12.0", featureSuffix: null, minimumStability: Stable
     * - "^2.12.0-feature" -> baseVersion: "^2.12.0", featureSuffix: "feature", minimumStability: Stable
     * - "^2.12.0-my-feature" -> baseVersion: "^2.12.0", featureSuffix: "my-feature", minimumStability: Stable
     * - "^2.12.0@beta" -> baseVersion: "^2.12.0", featureSuffix: null, minimumStability: Beta
     * - "^2.12.0-beta" -> baseVersion: "^2.12.0", featureSuffix: null, minimumStability: Beta (auto-converted)
     * - "^2.12.0-feature@beta" -> baseVersion: "^2.12.0", featureSuffix: "feature", minimumStability: Beta
     * - "^2.12.0-my-beta-feature@stable" -> baseVersion: "^2.12.0-my-beta", featureSuffix: "feature", minimumStability: Stable
     *
     * @param string $constraint Version constraint string
     * @return self Parsed version constraint
     * @throws \InvalidArgumentException If constraint syntax is invalid
     */
    public static function fromConstraintString(string $constraint): self
    {
        return new self(\trim($constraint));
    }

    /**
     * Checks if the given version satisfies this constraint.
     *
     * @param Version $version Version to check against this constraint
     * @return null|bool True if the version satisfies the constraint, false if it does not,
     *         null if the version is invalid or not applicable.
     */
    public function isSatisfiedBy(Version $version): ?bool
    {
        $number = $version->number;
        if ($number === null) {
            return false;
        }

        // Check if a version satisfies the base version constraint
        if (Semver::satisfies($number, $this->versionConstraint) === false) {
            return false;
        }

        // Check if the version satisfies the feature suffix constraint
        if ($this->featureSuffix !== null) {
            if (!\str_contains((string) $version->suffix, $this->featureSuffix)) {
                return false;
            }
        }

        // Check if the version satisfies the stability constraint
        $stability = $version->stability ?? Stability::Stable;
        return $stability->meetsMinimum($this->minimumStability);
    }

    /**
     * @return non-empty-string
     */
    public function __toString(): string
    {
        return $this->origin;
    }
}
