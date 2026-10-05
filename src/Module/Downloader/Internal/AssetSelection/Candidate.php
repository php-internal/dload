<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\Downloader\Internal\AssetSelection;

use Internal\DLoad\Module\Repository\AssetInterface;

/**
 * Release asset taking part in the selection, with the ranks the rules gave it.
 *
 * @internal
 * @psalm-internal Internal\DLoad\Module\Downloader
 */
final class Candidate
{
    /**
     * @param int<0, max> $position Position of the asset in the release, the last tie-breaker.
     * @param list<int> $ranks One key per ranking rule in the pipeline order; lower is better.
     */
    public function __construct(
        public readonly AssetInterface $asset,
        public readonly AssetName $name,
        public readonly int $position,
        public readonly array $ranks = [],
    ) {}

    /**
     * @param list<non-empty-string> $extensions File extensions without the leading dot.
     */
    public function hasExtension(array $extensions): bool
    {
        $name = \strtolower($this->asset->getName());
        foreach ($extensions as $extension) {
            if (\str_ends_with($name, '.' . $extension)) {
                return true;
            }
        }

        return false;
    }

    public function withRank(int $rank): self
    {
        return new self($this->asset, $this->name, $this->position, [...$this->ranks, $rank]);
    }
}
