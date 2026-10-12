<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\Repository\Internal;

use Internal\DLoad\Module\Registry\Record\ReleasePage;
use Internal\DLoad\Module\Registry\ReleaseSource;
use Internal\DLoad\Module\Repository\Exception\RepositoryException;

/**
 * Source over an API that lists releases in numbered pages of a fixed size.
 *
 * Translates a release offset into the page number and the number of releases to drop from
 * that page. The page size is the one the API is asked for, until the first page shows that
 * the server serves fewer releases per page, as a mirror or a server that caps `per_page` does.
 * Only the first page can tell: it is the one page whose start is known, so its length is the
 * page size and not the sign of a release filtered out after paging.
 *
 * The page size is learned per instance, not stored: the registry maps offsets through the
 * source and knows nothing of pages. So a server capping `per_page` costs the first page again
 * on every run that does not fetch it anyway, and a server serving more releases per page than
 * asked for is not supported: GitHub and GitLab both stop at the number asked for.
 *
 * @internal
 * @psalm-internal Internal\DLoad\Module\Repository
 */
final class PagedReleaseSource implements ReleaseSource
{
    /** @var int<1, max> */
    private int $perPage;

    /**
     * @param \Closure(int<1, max>): \Generator<int, ReleasePage, mixed, mixed> $pages Lists the
     *        pages from the given one on, requesting each one when reached.
     * @param int<1, max> $perPage Number of releases the API is asked for per page.
     */
    public function __construct(
        private readonly \Closure $pages,
        int $perPage,
    ) {
        $this->perPage = $perPage;
    }

    /**
     * @throws RepositoryException
     */
    public function pages(int $offset = 0): \Generator
    {
        $first = $this->pageOf($offset);
        $pages = ($this->pages)($first);
        $page = $pages->current();

        if ($first === 1 && $page !== null && !$page->last && $this->learn($page) && $this->pageOf($offset) !== 1) {
            $pages = ($this->pages)($this->pageOf($offset));
            $page = $pages->current();
        }

        // The generator is walked by hand: one that ended before its first page is closed, and
        // `foreach` cannot start it over
        $skip = $offset % $this->perPage;
        while ($page !== null) {
            yield $skip === 0 ? $page : self::from($page, $skip);
            $skip = 0;

            $pages->next();
            $page = $pages->current();
        }
    }

    /**
     * Drops the releases before the given position of the page.
     *
     * The unreadable releases of a page are not in its list, yet they hold positions on it.
     * They are taken as lying before the position: when one lies after it instead, a release is
     * repeated rather than lost, and the registry drops repeated ones.
     *
     * @param int<0, max> $skip
     */
    private static function from(ReleasePage $page, int $skip): ReleasePage
    {
        return new ReleasePage(\array_slice($page->releases, \max(0, $skip - $page->skipped)), $page->last, $page->skipped);
    }

    /**
     * Takes the length of a page followed by another one as the page size; tells whether it changed.
     */
    private function learn(ReleasePage $page): bool
    {
        $size = \count($page->releases) + $page->skipped;
        if ($size < 1 || $size >= $this->perPage) {
            return false;
        }

        $this->perPage = $size;

        return true;
    }

    /**
     * @param int<0, max> $offset
     * @return int<1, max>
     */
    private function pageOf(int $offset): int
    {
        /** @var int<1, max> */
        return \intdiv($offset, $this->perPage) + 1;
    }
}
