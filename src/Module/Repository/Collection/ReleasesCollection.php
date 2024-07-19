<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\Repository\Collection;

use Internal\DLoad\Module\Common\Stability;
use Internal\DLoad\Module\Repository\Internal\Collection;
use Internal\DLoad\Module\Repository\ReleaseInterface;

/**
 * @template-extends Collection<ReleaseInterface>
 * @internal
 * @psalm-internal Internal\DLoad\Module
 */
final class ReleasesCollection extends Collection
{
    /**
     * @param non-empty-string $constraint
     * @return $this
     */
    public function satisfies(string $constraint): self
    {
        return $this->filter(static fn(ReleaseInterface $r): bool => $r->satisfies($constraint));
    }

    /**
     * @param non-empty-string $constraint
     * @return $this
     */
    public function notSatisfies(string $constraint): self
    {
        return $this->except(static fn(ReleaseInterface $r): bool => $r->satisfies($constraint));
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
