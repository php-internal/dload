<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\Repository\Internal;

/**
 * Generic collection base class with filtering, mapping, and traversal capabilities.
 *
 * This abstract class provides common collection functionality for specialized
 * collections like ReleasesCollection and AssetsCollection.
 *
 * ```php
 * // Creating a collection from an array
 * $collection = SomeCollection::create($itemsArray);
 *
 * // Creating a collection from a generator function
 * $collection = SomeCollection::from(function() {
 *     yield new Item('one');
 *     yield new Item('two');
 * });
 *
 * // Finding the first item matching a condition
 * $item = $collection->first(fn($item) => $item->getName() === 'specific');
 * ```
 *
 * @template T
 * @template-implements \IteratorAggregate<array-key, T>
 *
 * @internal
 * @psalm-internal Internal\DLoad\Module\Repository
 */
abstract class Collection implements \IteratorAggregate, \Countable
{
    /**
     * Array of filter callbacks to apply when iterating
     *
     * @var array<callable(T): bool>
     */
    protected array $filters = [];

    /**
     * @param array<T>|CachedGenerator<T> $items Collection items
     */
    final public function __construct(
        protected readonly array|CachedGenerator $items,
    ) {}

    /**
     * Creates a new collection from various sources.
     *
     * Supports creating from an existing collection, a traversable,
     * an array, or a generator function.
     *
     * @template TNew
     *
     * @param iterable<TNew> $items Source of items
     * @return static<TNew> New collection instance
     * @throws \InvalidArgumentException If the input cannot be converted to a collection
     */
    public static function create(mixed $items): static
    {
        return match (true) {
            $items instanceof static => $items,
            \is_array($items) => new static($items),
            $items instanceof \Traversable => new static(new CachedGenerator($items)),
            $items instanceof \Closure => static::create($items()),
            default => throw new \InvalidArgumentException(
                \sprintf('Unsupported iterable type %s.', \get_debug_type($items)),
            ),
        };
    }

    /**
     * Adds a filter to the collection.
     * This does not immediately apply the filter, but stores it for later use during iteration.
     *
     * @param callable(T): bool $filter Function that returns true for items to keep
     * @return $this New filtered collection
     */
    public function filter(callable $filter): static
    {
        // For iterables or collections with existing filters, add the filter to the pipeline
        $clone = clone $this;
        $clone->filters[] = $filter;
        return $clone;
    }

    /**
     * Maps each item in the collection using the provided callback.
     *
     * @param callable(T): mixed $map Function that transforms each item
     * @return $this New collection with mapped items
     */
    public function map(callable $map): static
    {
        // For iterables or collections with filters, we need to materialize and map
        $items = [];
        foreach ($this as $item) {
            $items[] = $map($item);
        }

        return new static($items);
    }

    /**
     * Creates a new collection excluding items that match the filter.
     *
     * @param callable(T): bool $filter Function that returns true for items to exclude
     * @return $this New filtered collection
     *
     * @psalm-suppress MissingClosureParamType
     * @psalm-suppress MixedArgument
     */
    public function except(callable $filter): static
    {
        $callback = static fn(...$args): bool => !$filter(...$args);
        return $this->filter($callback);
    }

    /**
     * Returns the first item that matches the filter, or null if no matches.
     *
     * If no filter is provided, returns the first item in the collection.
     *
     * @param null|callable(T): bool $filter Optional filter function
     * @return T|null First matching item or null
     */
    public function first(?callable $filter = null): ?object
    {
        $combinedFilter = $this->getCombinedFilter($filter);
        if ($combinedFilter === null) {
            return $this->items instanceof CachedGenerator
                ? $this->items->first()
                : ($this->items === [] ? null : \reset($this->items));
        }

        // For iterables, iterate until we find a match
        foreach ($this->getIterator() as $item) {
            if ($filter === null || $filter($item)) {
                return $item;
            }
        }

        return null;
    }

    /**
     * Returns the first matching item or a default value from the callback.
     *
     * @param callable(): T $otherwise Function to provide default value if no match found
     * @param null|callable(T): bool $filter Optional filter function
     * @return T First matching item or default value
     */
    public function firstOr(callable $otherwise, ?callable $filter = null): object
    {
        return $this->first($filter) ?? $otherwise();
    }

    /**
     * Returns an iterator for traversing the collection.
     *
     * @return \Traversable<array-key, T> Collection iterator
     */
    public function getIterator(): \Traversable
    {
        $combinedFilter = $this->getCombinedFilter();

        // If no filters, return the items directly
        if ($combinedFilter === null) {
            yield from $this->items;
            return;
        }

        // Apply filters during iteration
        foreach ($this->items as $item) {
            if ($combinedFilter($item)) {
                yield $item;
            }
        }
    }

    /**
     * Returns the number of items in the collection.
     *
     * @return int<0, max> Item count
     */
    public function count(): int
    {
        if ($this->filters === []) {
            return $this->items instanceof CachedGenerator
                ? $this->items->count()
                : \count($this->items);
        }

        // For iterables or with filters, we need to count matching items
        $count = 0;
        foreach ($this as $item) {
            $count++;
        }

        return $count;
    }

    /**
     * Executes a callback if the collection is empty.
     *
     * @param callable $then Function to execute if collection is empty
     * @return $this This collection instance
     */
    public function whenEmpty(callable $then): static
    {
        $this->empty() and $then();
        return $this;
    }

    /**
     * Checks if the collection is empty.
     *
     * @return bool True if the collection has no items
     */
    public function empty(): bool
    {
        if ($this->filters === []) {
            return $this->items instanceof CachedGenerator
                ? $this->items->isEmpty()
                : $this->items === [];
        }

        // For iterables or with filters, try to get the first item
        return $this->first() === null;
    }

    /**
     * Converts the collection to an indexed array.
     *
     * @return array<T> Array of collection items
     */
    public function toArray(): array
    {
        return \iterator_to_array($this->getIterator());
    }

    /**
     * Combines all filters with an optional additional filter.
     * Returns null if there are no filters to apply.
     *
     * @param null|callable(T): bool $additionalFilter
     * @return null|callable(T): bool
     */
    private function getCombinedFilter(?callable $additionalFilter = null): ?callable
    {
        if ($this->filters === []) {
            return $additionalFilter;
        }

        // Combine collection filters with additional filter
        return function ($item) use ($additionalFilter) {
            foreach ($this->filters as $filter) {
                if (!$filter($item)) {
                    return false;
                }
            }

            return $additionalFilter === null ? true : $additionalFilter($item);
        };
    }
}
