<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\Repository\Internal;

/**
 * Paginator that allows to iterate over all pages.
 *
 * @template TItem
 * @implements \IteratorAggregate<TItem>
 *
 * @internal
 * @psalm-internal Internal\DLoad\Module\Repository
 */
final class Paginator implements \IteratorAggregate, \Countable
{
    /** @var list<TItem> */
    private array $collection;

    /** @var self<TItem>|null */
    private ?self $nextPage = null;

    private ?int $totalItems = null;

    /**
     * @param \Generator<array-key, list<TItem>, mixed, mixed> $loader
     * @param int<1, max> $pageNumber
     * @param null|\Closure(): int<0, max> $counter
     */
    private function __construct(
        private readonly \Generator $loader,
        private readonly int $pageNumber,
        private ?\Closure $counter,
    ) {
        $this->collection = $loader->valid() ? $loader->current() : [];
    }

    /**
     * @template TInitItem
     *
     * @param \Generator<array-key, list<TInitItem>> $loader
     * @param null|callable(): int<0, max> $counter Returns total number of items.
     *
     * @return self<TInitItem>
     */
    public static function createFromGenerator(\Generator $loader, ?callable $counter): self
    {
        return new self($loader, 1, $counter === null ? null : $counter(...));
    }

    /**
     * Load next page.
     *
     * @return self<TItem>|null
     */
    public function getNextPage(): ?self
    {
        if ($this->nextPage !== null) {
            return $this->nextPage;
        }

        $this->loader->next();
        if (!$this->loader->valid()) {
            return null;
        }
        $this->nextPage = new self($this->loader, $this->pageNumber + 1, $this->counter);
        /** @psalm-suppress UnsupportedPropertyReferenceUsage */
        $this->nextPage->counter = &$this->counter;

        return $this->nextPage;
    }

    /**
     * @return array<TItem>
     */
    public function getPageItems(): array
    {
        return $this->collection;
    }

    /**
     * Iterate all items from current page and all next pages.
     *
     * @return \Traversable<TItem>
     */
    public function getIterator(): \Traversable
    {
        $paginator = $this;
        while ($paginator !== null) {
            foreach ($paginator->getPageItems() as $item) {
                yield $item;
            }

            $paginator = $paginator->getNextPage();
        }
    }

    /**
     * @return int<1, max>
     */
    public function getPageNumber(): int
    {
        return $this->pageNumber;
    }

    /**
     * Value is cached in all produced pages after first call in any page.
     *
     * Note: the method may call yet another RPC to get total number of items.
     * It means that the result may be different from the number of items at the moment of the pagination start.
     *
     * @throws \LogicException If counter is not set.
     */
    public function count(): int
    {
        if ($this->totalItems !== null) {
            return $this->totalItems;
        }

        if ($this->counter === null) {
            throw new \LogicException('Paginator does not support counting.');
        }

        return $this->totalItems = ($this->counter)();
    }
}
