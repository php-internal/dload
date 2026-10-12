<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\Version;

use Internal\DLoad\Module\Common\Stability;

/**
 * Version constraint DTO for parsing and handling complex version requirements.
 *
 * Encapsulates version constraint logic supporting:
 * - Version ranges in the Composer syntax, see {@see Range}: ^2.12.0, ~1.20, >=1.0 <2.0 || ^3.0
 * - Feature suffixes of ranges: ^2.12.0-feature, ~1.20.0-hotfix, ^1.0.0-my-feature
 * - Minimum stability, explicit or taken from the pre-releases of the constraint: ^2.12.0@beta, ^2.12.0-beta
 * - Combined constraints: ^2.12.0-feature@beta
 * - Exact releases, a pre-release or a tail included: 3.5.0-beta.1, 2.0-beta, 1.0.0-1, 2.12.0-hotfix, 2.0.0rc1
 * - Pre-release bounds of ranges and comparisons: >=3.5.0-beta.1 <3.5.0-RC1, ^1.0 || ^2.0-beta.1, >2.0-beta, ^2.0.0rc1
 * - Build metadata, which does not count: >=1.2.3+build.5, 3.5.0-beta.1+5
 *
 * A stability keyword at the start or the end of a feature suffix sets the minimum stability
 * unless an explicit one is given: ^2.12.0-my-feature-beta.
 *
 * @internal
 */
final class Constraint implements \Stringable
{
    /** @var non-empty-string $versionConstraint Base version constraint (e.g. "^2.12.0") */
    public readonly string $versionConstraint;

    /**
     * @var non-empty-string|null $featureSuffix Optional feature suffix (e.g. "feature", "my-feature")
     *      the version suffix must contain, in any case.
     *      If null, no feature suffix is specified.
     */
    public readonly ?string $featureSuffix;

    /** @var Stability $minimumStability Minimum stability level for this constraint */
    public readonly Stability $minimumStability;

    /**
     * @var PreRelease|null $preRelease Pre-release of a single version the constraint is bound to,
     *      e.g. `beta.1` of `3.5.0-beta.1`. Null when there is none, when a tail follows it, or when
     *      the constraint has several terms: their pre-releases stay in {@see $versionConstraint}.
     */
    public readonly ?PreRelease $preRelease;

    /** Versions the constraint accepts, the pre-release included. */
    private readonly Range|Comparison $matcher;

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

        # Build metadata does not count, and a hyphen within it, as in "1.2.3+build-5", starts no feature suffix
        $origin = (string) \preg_replace('/(?<=[a-z\d])' . Version::buildPattern() . '(?=$|[\s,|])/i', '', $origin);

        # A single version with a pre-release or any other tail names one release: "2.0-beta" accepts neither
        # "2.0.0-beta.3" nor "2.0.0", and "2.12.0-hotfix" not "2.12.0-hotfix.2"; a bare number covers every build
        # of that number, see Comparison. In a range, a pre-release bound, a bare "-beta" included, is a part of
        # the version as in Composer, ">2.0-beta" accepts "2.0.0-RC1", and any other suffix is a feature.
        # Without a hyphen, a tail starts with a letter, as in "2.0.0rc1" or "1.0.0.Final"; ".x" is a wildcard, a glued "x" a patch letter.
        $matcher = null;
        $tail = '';
        $preRelease = null;
        if (\preg_match(
            '/^(?:==?)?\s*v?(\d+(?:\.\d+)*)(-[\w.-]+|[._]?(?=[a-z])(?!(?<=\.)x(?![a-z\d]))[\w.-]+)?$/i',
            $origin,
            $exact,
        ) === 1) {
            /** @var non-empty-string $number */
            $number = $exact[1];
            $tail = $exact[2] ?? '';
            $matcher = Comparison::create(Operator::Equal, $number, $tail);
            $stability ??= $matcher->bound->stability;
            $tail !== '' && $matcher->bound->suffix === null and $preRelease = $matcher->bound->preRelease;
        } elseif (($bounds = self::preReleaseBounds($origin)) !== []) {
            $matcher = Range::fromString($origin);
            $stability ??= self::lowestStability($bounds);
            $singleTerm = \preg_match('/[\s,|]/', (string) \preg_replace('/^[<>=!~^]*\s*/', '', $origin)) !== 1;
            $singleTerm and $tail = $bounds[0];
            $preRelease = PreRelease::fromString(\ltrim($tail, '-._'));
        }

        $preRelease === null or $origin = \substr($origin, 0, -\strlen($tail));
        $this->preRelease = $preRelease;

        # The hyphen of a range like "1.0 - 2.0" stands between spaces and does not start a suffix
        [$version, $suffix] = $matcher !== null || \preg_match('/\s-\s/', $origin) === 1
            ? [$origin, '']
            : \explode('-', $origin, 2) + [1 => ''];
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
        $suffix === '' and $suffix = null;

        // Determine final stability (explicit takes precedence over implicit)
        $stability ??= $suffix === null ? Stability::Stable : Stability::Preview;

        $version === '' and throw new \InvalidArgumentException('Base version cannot be empty.');
        $this->matcher = $matcher ?? Range::fromString($version);

        $this->versionConstraint = $version;
        $this->featureSuffix = $suffix;
        $this->minimumStability = $stability;
    }

    /**
     * Parse version constraint string into DTO.
     *
     * Examples:
     * - "^2.12.0" -> baseVersion: "^2.12.0", featureSuffix: null, minimumStability: Stable
     * - "^2.12.0-feature" -> baseVersion: "^2.12.0", featureSuffix: "feature", minimumStability: Preview
     * - "^2.12.0-my-feature" -> baseVersion: "^2.12.0", featureSuffix: "my-feature", minimumStability: Preview
     * - "^2.12.0@beta" -> baseVersion: "^2.12.0", featureSuffix: null, minimumStability: Beta
     * - "^2.12.0-beta" -> baseVersion: "^2.12.0", preRelease: beta, minimumStability: Beta
     * - "^2.12.0-beta@alpha" -> baseVersion: "^2.12.0", preRelease: beta, minimumStability: Alpha
     * - "^2.12.0-feature@beta" -> baseVersion: "^2.12.0", featureSuffix: "feature", minimumStability: Beta
     * - "^2.12.0-my-beta-feature@stable" -> baseVersion: "^2.12.0", featureSuffix: "my-beta-feature", minimumStability: Stable
     * - "3.5.0-beta.1" -> baseVersion: "3.5.0", preRelease: beta.1, minimumStability: Beta
     * - "2.12.0-hotfix" -> baseVersion: "2.12.0-hotfix", featureSuffix: null, minimumStability: Preview
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
     * A version without a number satisfies no constraint.
     */
    public function isSatisfiedBy(Version $version): bool
    {
        if ($version->number === null || !$this->matcher->isSatisfiedBy($version)) {
            return false;
        }

        // Check if the version satisfies the feature suffix constraint
        if ($this->featureSuffix !== null) {
            if (\stripos((string) $version->suffix, $this->featureSuffix) === false) {
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

    /**
     * Pre-releases of the version bounds with their separators, like `-1`, `-beta.1` and `rc2`
     * of `>=1.0.0-1 <2.0.0-beta.1 || ^2.0rc2`, see {@see Range::preReleasePattern()}.
     * A bound follows the version number right away: neither a suffix that runs on past a pre-release,
     * like `-beta.1-feature`, nor a pre-release within a suffix, like `-1` of `1.0.0-x64-1`, is a bound.
     *
     * @return list<non-empty-string>
     */
    private static function preReleaseBounds(string $constraint): array
    {
        \preg_match_all(
            '/(?:^|[\s,|])[<>=!~^]*v?\d+(?:\.\d+)*(' . Range::preReleasePattern() . ')(?=$|[\s,|])/i',
            $constraint,
            $matches,
        );

        /** @var list<non-empty-string> */
        return $matches[1];
    }

    /**
     * A numeric tail, like `-1` of `1.0.0-1`, is a {@see Stability::Preview}, as {@see Version} reads it.
     *
     * @param non-empty-list<non-empty-string> $preReleases
     */
    private static function lowestStability(array $preReleases): Stability
    {
        $stabilities = \array_map(
            static fn(string $preRelease): Stability => PreRelease::fromString(\ltrim($preRelease, '-._'))?->stability
                ?? Stability::Preview,
            $preReleases,
        );
        \usort($stabilities, static fn(Stability $a, Stability $b): int => $a->getWeight() <=> $b->getWeight());

        return $stabilities[0];
    }
}
