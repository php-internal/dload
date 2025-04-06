<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\Repository\Internal;

/**
 * Wraps a generator or any traversable and caches yielded values.
 * This prevents values from being "consumed" when iterating.
 *
 * @template T
 * @template-implements \IteratorAggregate<array-key, T>
 *
 * @internal
 * @psalm-internal Internal\DLoad\Module\Repository
 */
final class CachedGenerator implements \IteratorAggregate
{
    /**
     * @var array<int, T> Cached items from the generator
     */
    private array $cache = [];

    private ?\Generator $generator;
    private bool $inited = false;

    /**
     * @param \Traversable<array-key, T> $generator The generator to cache
     */
    public function __construct(
        \Traversable $generator,
    ) {
        $this->generator = (static function (\Traversable $generator) {
            foreach ($generator as $key => $value) {
                yield $key => $value;
            }
        })($generator);
    }

    /**
     * Returns an iterator that yields the cached items and continues
     * fetching from the generator when the cache is exhausted.
     *
     * @return \Traversable<array-key, T>
     */
    public function getIterator(): \Traversable
    {
        $index = 0;

        // First yield all cached items
        while ($index < \count($this->cache)) {
            yield $this->cache[$index];
            $index++;
        }

        start:
        $next = $this->rollItem();
        if ($this->generator === null) {
            return;
        }

        yield $next;
        goto start;
    }

    /**
     * Returns the first item in the cache or from the generator.
     *
     * @return T|null The first item or null if the generator is empty
     */
    public function first(): mixed
    {
        // If we have cached items, return the first one
        return $this->cache === []
            ? $this->rollItem()
            : $this->cache[0];
    }

    /**
     * Returns whether the generator has any items.
     *
     * @return bool True if there are items, false otherwise
     */
    public function isEmpty(): bool
    {
        // If we already have items, it's not empty
        if (!empty($this->cache)) {
            return false;
        }

        // Try to get the first item to determine if it's empty
        return $this->first() === null;
    }

    /**
     * Returns the count of items in the generator.
     *
     * @return int<0, max> The number of items
     */
    public function count(): int
    {
        if ($this->generator !== null) {
            while ($this->generator !== null && $this->generator->valid()) {
                $this->rollItem();
            }
        }

        return \count($this->cache);
    }

    /**
     * Get the next item from the generator.
     *
     * @return T The next item
     */
    private function rollItem(): mixed
    {
        if ($this->inited) {
            if ($this->generator === null) {
                return null;
            }

            try {
                $this->generator->next();
                if (!$this->generator->valid()) {
                    $this->generator = null;
                    return null;
                }
            } catch (\Throwable $e) {
                $this->generator = null;
                throw $e;
            }
        }

        $this->inited = true;
        /** @var T $next */
        $next = $this->generator->current();
        $this->cache[] = $next;

        return $next;
    }
}
