<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\Registry;

use Internal\DLoad\Module\Registry\Record\ReleaseRecord;
use Internal\DLoad\Module\Repository\Exception\RepositoryException;

/**
 * Local database of software versions: the releases every known repository offers.
 *
 * Versions never expire. What expires is the last check against the source: while it is recent,
 * repeated runs are served from the database without a single request. When it is older than the
 * configured TTL, only the releases published since the last check are fetched.
 *
 * ```php
 * foreach ($registry->releases($id, $source) as $page) {
 *     foreach ($page as $record) {
 *         // ...
 *     }
 * }
 * ```
 */
interface VersionRegistry
{
    /**
     * Lists the releases of a repository newest first, page by page.
     *
     * Older releases that are not in the database yet are loaded from the source only when the
     * iteration reaches them, so a consumer that stops early costs no extra request.
     *
     * @return \Generator<int, list<ReleaseRecord>, mixed, void>
     * @throws RepositoryException When releases cannot be obtained from either the database or the source.
     */
    public function releases(RepositoryId $id, ReleaseSource $source): \Generator;

    /**
     * Records that a software package is served from the repository.
     *
     * @param non-empty-string $software Software identifier.
     */
    public function attach(string $software, RepositoryId $id): void;
}
