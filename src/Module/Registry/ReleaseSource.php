<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\Registry;

use Internal\DLoad\Module\Registry\Record\ReleasePage;
use Internal\DLoad\Module\Repository\Exception\RepositoryException;

/**
 * Origin of the releases of one repository: the provider API behind the registry.
 *
 * The source lists releases newest first, page by page, and must not perform a request before
 * the corresponding page is actually iterated, so the registry can stop as early as it likes.
 *
 * @internal
 */
interface ReleaseSource
{
    /**
     * Lists releases newest first in whole pages, from the page holding the given position on.
     *
     * Positions count every release of the listing, unreadable ones included. The first page may
     * start before the position; when the listing ends before it, its last page comes first.
     * A page is requested only when the generator advances to it. The generator ends when the
     * listing ends.
     *
     * @param int<0, max> $offset Listing position of the first release wanted.
     * @return \Generator<int, ReleasePage, mixed, void> Pages of releases.
     * @throws RepositoryException When a page cannot be loaded.
     */
    public function pages(int $offset = 0): \Generator;
}
