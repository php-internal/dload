<?php

declare(strict_types=1);

namespace Internal\DLoad\Tests\Unit\Module\Registry\Stub;

use Internal\DLoad\Module\Registry\Record\RepositoryRecord;
use Internal\DLoad\Module\Registry\RegistryStorage;
use Internal\DLoad\Module\Registry\RepositoryId;

/**
 * Storage that keeps records in memory and counts the writes, so a test can assert what the
 * registry persisted and when.
 */
final class InMemoryRegistryStorage implements RegistryStorage
{
    /** @var array<string, RepositoryRecord> */
    public array $records = [];

    /** @var int<0, max> */
    public int $saves = 0;

    public bool $failOnSave = false;

    public function load(RepositoryId $id): ?RepositoryRecord
    {
        return $this->records[(string) $id] ?? null;
    }

    public function save(RepositoryRecord $record): void
    {
        $this->failOnSave and throw new \RuntimeException('Storage is read-only.');

        ++$this->saves;
        $this->records[(string) $record->id] = $record;
    }

    public function all(): iterable
    {
        yield from \array_values($this->records);
    }

    public function remove(RepositoryId $id): void
    {
        unset($this->records[(string) $id]);
    }

    public function clear(): void
    {
        $this->records = [];
    }
}
