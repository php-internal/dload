<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\Repository\Internal;

use Internal\DLoad\Module\Common\Stability;
use Internal\DLoad\Module\Repository\ReleaseInterface;

/**
 * @template-extends Collection<ReleaseInterface>
 * @psalm-import-type StabilityType from Stability
 * @internal
 * @psalm-internal Internal\DLoad\Module\Repository
 */
final class ReleasesCollection extends Collection
{
    /**
     * @param string ...$constraints
     * @return $this
     */
    public function satisfies(string ...$constraints): self
    {
        $result = $this;

        foreach ($this->constraints($constraints) as $constraint) {
            $result = $result->filter(static fn(ReleaseInterface $r): bool => $r->satisfies($constraint));
        }

        return $result;
    }

    /**
     * @param string ...$constraints
     * @return $this
     */
    public function notSatisfies(string ...$constraints): self
    {
        $result = $this;

        foreach ($this->constraints($constraints) as $constraint) {
            $result = $result->except(static fn(ReleaseInterface $r): bool => $r->satisfies($constraint));
        }

        return $result;
    }

    /**
     * @return $this
     */
    public function withAssets(): self
    {
        return $this->filter(
            static fn(ReleaseInterface $r): bool => ! $r->getAssets()
                ->empty(),
        );
    }

    /**
     * @return $this
     */
    public function sortByVersion(): self
    {
        $result = $this->items;

        $sort = function (ReleaseInterface $a, ReleaseInterface $b): int {
            return \version_compare($this->comparisonVersionString($b), $this->comparisonVersionString($a));
        };

        \uasort($result, $sort);

        return new self($result);
    }

    /**
     * @return $this
     */
    public function stable(): self
    {
        return $this->stability(Stability::Stable);
    }

    /**
     * @return $this
     */
    public function stability(Stability $stability): self
    {
        return $this->filter(static fn(ReleaseInterface $rel): bool => $rel->getStability() === $stability);
    }

    /**
     * @return $this
     */
    public function minimumStability(Stability $stability): self
    {
        $weight = $stability->getWeight();
        return $this->filter(
            static fn(ReleaseInterface $release): bool => $release->getStability()->getWeight() >= $weight,
        );
    }

    /**
     * @param array<string> $constraints
     * @return array<string>
     */
    private function constraints(array $constraints): array
    {
        $result = [];

        foreach ($constraints as $constraint) {
            foreach (\explode('|', $constraint) as $expression) {
                $result[] = $expression;
            }
        }

        return \array_unique(\array_filter(\array_map('\\trim', $result)));
    }

    /**
     * @return non-empty-string
     */
    private function comparisonVersionString(ReleaseInterface $release): string
    {
        $stability = $release->getStability();

        return \ltrim(\str_replace(
            '-' . $stability->value,
            '.' . $stability->getWeight() . '.',
            $release->getVersion(),
        ), 'v');
    }
}
