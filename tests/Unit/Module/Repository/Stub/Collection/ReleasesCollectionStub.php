<?php

declare(strict_types=1);

namespace Internal\DLoad\Tests\Unit\Module\Repository\Stub\Collection;

use Internal\DLoad\Module\Repository\Collection\ReleasesCollection;
use Internal\DLoad\Module\Repository\Internal\Collection;
use Internal\DLoad\Module\Repository\ReleaseInterface;

/**
 * Stub implementation of ReleasesCollection for testing.
 */
final class ReleasesCollectionStub extends Collection
{
    /** @var ReleaseInterface[] */
    private array $releases;

    public function count(): int
    {
        return \count($this->releases);
    }

    public function getIterator(): \Traversable
    {
        return new \ArrayIterator($this->releases);
    }

    public function satisfies(string $constraint): ReleasesCollection
    {
        // Simple implementation for testing - returns same collection
        return $this;
    }

    public function sortByVersion(bool $descending = true): ReleasesCollection
    {
        // Simple implementation for testing - returns same collection
        return $this;
    }

    public function last(): ?ReleaseInterface
    {
        if (empty($this->releases)) {
            return null;
        }

        return $this->releases[\count($this->releases) - 1];
    }
}
