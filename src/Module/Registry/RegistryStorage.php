<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\Registry;

use Internal\DLoad\Module\Registry\Record\RepositoryRecord;

/**
 * Persistence of the version registry.
 *
 * Implementations must never let a storage failure escape as an exception from `load()`:
 * a broken or unreadable record is reported as missing, because the registry is an optimisation
 * and must not turn a working download into a failed one.
 */
interface RegistryStorage
{
    /**
     * Returns the stored record, or `null` when there is none or it cannot be read.
     */
    public function load(RepositoryId $id): ?RepositoryRecord;

    /**
     * Stores the record, replacing the previous one.
     *
     * @throws \RuntimeException When the record cannot be written.
     */
    public function save(RepositoryRecord $record): void;

    /**
     * Lists every readable record.
     *
     * @return iterable<RepositoryRecord>
     */
    public function all(): iterable;

    /**
     * Removes the record of a repository; a missing record is not an error.
     */
    public function remove(RepositoryId $id): void;

    /**
     * Removes every record.
     */
    public function clear(): void;
}
