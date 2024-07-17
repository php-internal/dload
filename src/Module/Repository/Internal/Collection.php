<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\Repository\Internal;

/**
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
     * @param array<T> $items
     */
    final public function __construct(array $items)
    {
        $this->items = $items;
    }

    /**
     * @param self|iterable|\Closure $items
     * @return static
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
     * @param \Closure $generator
     * @return static
     */
    public static function from(\Closure $generator): static
    {
        return static::create($generator());
    }

    /**
     * @param callable(T): bool $filter
     * @return $this
     */
    public function filter(callable $filter): static
    {
        return new static(\array_filter($this->items, $filter));
    }

    /**
     * @param callable(T): mixed $map
     * @return $this
     */
    public function map(callable $map): static
    {
        return new static(\array_map($map, $this->items));
    }

    /**
     * @param callable(T): bool $filter
     * @return $this
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
     * @param null|callable(T): bool $filter
     * @return T|null
     */
    public function first(callable $filter = null): ?object
    {
        $self = $filter === null ? $this : $this->filter($filter);

        return $self->items === [] ? null : \reset($self->items);
    }

    /**
     * @param callable(): T $otherwise
     * @param null|callable(T): bool $filter
     * @return T
     */
    public function firstOr(callable $otherwise, callable $filter = null): object
    {
        return $this->first($filter) ?? $otherwise();
    }

    public function getIterator(): \Traversable
    {
        return new \ArrayIterator($this->items);
    }

    public function count(): int
    {
        return \count($this->items);
    }

    /**
     * @param callable $then
     * @return $this
     */
    public function whenEmpty(callable $then): static
    {
        if ($this->empty()) {
            $then();
        }

        return $this;
    }

    public function empty(): bool
    {
        return $this->items === [];
    }

    /**
     * @return array<T>
     */
    public function toArray(): array
    {
        return \array_values($this->items);
    }
}
