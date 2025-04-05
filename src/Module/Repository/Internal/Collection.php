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
     * @var array<T>
     */
    protected array $items;

    /**
     * @param array<T> $items Collection items
     */
    final public function __construct(array $items)
    {
        $this->items = $items;
    }

    /**
     * Creates a new collection from various sources.
     *
     * Supports creating from an existing collection, a traversable,
     * an array, or a generator function.
     *
     * @param self|iterable|\Closure $items Source of items
     * @return static New collection instance
     * @throws \InvalidArgumentException If the input cannot be converted to a collection
     */
    public static function create(mixed $items): static
    {
        return match (true) {
            $items instanceof static => $items,
            $items instanceof \Traversable => new static(\iterator_to_array($items)),
            \is_array($items) => new static($items),
            $items instanceof \Closure => static::from($items),
            default => throw new \InvalidArgumentException(
                \sprintf('Unsupported iterable type %s.', \get_debug_type($items)),
            ),
        };
    }

    /**
     * Creates a collection from a generator function.
     *
     * @param \Closure $generator Function that yields collection items
     * @return static New collection instance
     */
    public static function from(\Closure $generator): static
    {
        return static::create($generator());
    }

    /**
     * Filters the collection using the provided callback.
     *
     * @param callable(T): bool $filter Function that returns true for items to keep
     * @return $this New filtered collection
     */
    public function filter(callable $filter): static
    {
        return new static(\array_filter($this->items, $filter));
    }

    /**
     * Maps each item in the collection using the provided callback.
     *
     * @param callable(T): mixed $map Function that transforms each item
     * @return $this New collection with mapped items
     */
    public function map(callable $map): static
    {
        return new static(\array_map($map, $this->items));
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
        $callback = static fn(...$args): bool => ! $filter(...$args);

        return new static(\array_filter($this->items, $callback));
    }

    /**
     * Returns the first item that matches the filter, or null if no matches.
     *
     * If no filter is provided, returns the first item in the collection.
     *
     * @param null|callable(T): bool $filter Optional filter function
     * @return T|null First matching item or null
     */
    public function first(callable $filter = null): ?object
    {
        $self = $filter === null ? $this : $this->filter($filter);

        return $self->items === [] ? null : \reset($self->items);
    }

    /**
     * Returns the first matching item or a default value from the callback.
     *
     * @param callable(): T $otherwise Function to provide default value if no match found
     * @param null|callable(T): bool $filter Optional filter function
     * @return T First matching item or default value
     */
    public function firstOr(callable $otherwise, callable $filter = null): object
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
        return new \ArrayIterator($this->items);
    }

    /**
     * Returns the number of items in the collection.
     *
     * @return int<0, max> Item count
     */
    public function count(): int
    {
        return \count($this->items);
    }

    /**
     * Executes a callback if the collection is empty.
     *
     * @param callable $then Function to execute if collection is empty
     * @return $this This collection instance
     */
    public function whenEmpty(callable $then): static
    {
        if ($this->empty()) {
            $then();
        }

        return $this;
    }

    /**
     * Checks if the collection is empty.
     *
     * @return bool True if the collection has no items
     */
    public function empty(): bool
    {
        return $this->items === [];
    }

    /**
     * Converts the collection to an indexed array.
     *
     * @return array<T> Array of collection items
     */
    public function toArray(): array
    {
        return \array_values($this->items);
    }
}
