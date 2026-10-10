<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\Version;

/**
 * One comparison of a version constraint against a bound, e.g. `>=1.2.3` or `<3.5.0-beta.2`.
 *
 * A bound with a pre-release is a single release and compares as in sorting, tails included.
 * A bound without one names a version number: every build of that number counts as the number,
 * so `>=1.2` accepts `1.2.0-beta` and `<1.2` rejects it, as Composer does with `-dev` bounds.
 *
 * @internal
 */
final class Comparison
{
    private function __construct(
        public readonly Operator $operator,
        public readonly Version $bound,
        private readonly bool $withPreRelease,
    ) {}

    /**
     * @param non-empty-string $number Version number, e.g. `1.2.3`
     * @param string $preRelease Pre-release of the bound, e.g. `beta.1`; empty for none
     */
    public static function create(Operator $operator, string $number, string $preRelease = ''): self
    {
        return new self(
            $operator,
            Version::fromVersionString($preRelease === '' ? $number : "{$number}-{$preRelease}"),
            $preRelease !== '',
        );
    }

    public function isSatisfiedBy(Version $version): bool
    {
        return $this->operator->accepts(
            $this->withPreRelease ? $version->compare($this->bound) : $version->compareNumber($this->bound),
        );
    }
}
