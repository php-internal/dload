<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\Software;

use Internal\DLoad\Module\Config\Schema\Embed\Software;

/**
 * Software definitions collected from all sources.
 *
 * Several sources may declare the same id. Lookup and iteration return the entry of the strongest
 * {@see OriginKind}; the others stay in the collection, shadowed.
 *
 * ```php
 * $software = $collection->findSoftware('rr') ?? throw new \RuntimeException('Software not found');
 * ```
 *
 * @implements \IteratorAggregate<non-empty-string, Software>
 */
final class SoftwareCollection implements \IteratorAggregate, \Countable
{
    /** @var array<non-empty-string, non-empty-list<Entry>> Entries by software id, in the order they were added */
    private array $entries = [];

    private function __construct() {}

    public static function empty(): self
    {
        return new self();
    }

    /**
     * Adds definitions declared by one source.
     *
     * A definition replaces the one with the same id from the same origin.
     */
    public function with(Origin $origin, Software ...$software): self
    {
        $new = clone $this;
        foreach ($software as $item) {
            $id = $item->getId();
            $entries = \array_filter(
                $new->entries[$id] ?? [],
                static fn(Entry $entry): bool => !$entry->origin->equals($origin),
            );
            $entries[] = new Entry($item, $origin);
            $new->entries[$id] = \array_values($entries);
        }

        return $new;
    }

    /**
     * Finds the entry that wins for the given id.
     *
     * @param non-empty-string $id Software id: alias or lowercased name.
     */
    public function find(string $id): ?Entry
    {
        return isset($this->entries[$id]) ? self::strongest($this->entries[$id]) : null;
    }

    /**
     * @param non-empty-string $name Software id: alias or lowercased name.
     */
    public function findSoftware(string $name): ?Software
    {
        return $this->find($name)?->software;
    }

    /**
     * Yields the winning definition of every id.
     *
     * @return \Traversable<non-empty-string, Software>
     */
    public function getIterator(): \Traversable
    {
        foreach ($this->entries as $id => $entries) {
            yield $id => self::strongest($entries)->software;
        }
    }

    /**
     * @return int<0, max> Number of distinct software ids.
     */
    public function count(): int
    {
        return \count($this->entries);
    }

    /**
     * @param non-empty-list<Entry> $entries
     */
    private static function strongest(array $entries): Entry
    {
        $winner = $entries[0];
        foreach ($entries as $entry) {
            $entry->origin->kind->priority() < $winner->origin->kind->priority() and $winner = $entry;
        }

        return $winner;
    }
}
