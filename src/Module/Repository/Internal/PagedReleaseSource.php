<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\Repository\Internal;

use Internal\DLoad\Module\Registry\Record\ReleasePage;
use Internal\DLoad\Module\Registry\ReleaseSource;
use Internal\DLoad\Module\Repository\Exception\RepositoryException;

/**
 * Source over an API that lists releases in numbered pages of a fixed size.
 *
 * A listing position is mapped onto the page holding it by the page size the API is asked for.
 * A first page holding at most half of that, as a mirror or a server capping `per_page` serves,
 * tells the real size for the rest of the instance's life. A page only slightly shorter is taken
 * for releases filtered out after paging and changes nothing. A page size the source gets wrong
 * costs the registry a step to the neighbouring page, never a release.
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
        $number = $this->pageOf($offset);
        $pages = ($this->pages)($number);
        $page = $pages->current();

        if ($number === 1 && $page !== null && !$page->last && $this->learn($page) && $this->pageOf($offset) > 1) {
            $number = $this->pageOf($offset);
            $pages = ($this->pages)($number);
            $page = $pages->current();
        }

        // The listing ends before the position: it is listed from its last page
        while ($page === null && $number > 1) {
            $pages = ($this->pages)(--$number);
            $page = $pages->current();
        }

        // The generator is walked by hand: one that ended before its first page is closed, and
        // `foreach` cannot start it over
        $size = $this->perPage;
        while ($page !== null) {
            yield new ReleasePage($page->releases, $page->last, $page->skipped, ($number - 1) * $size);

            ++$number;
            $pages->next();
            $page = $pages->current();
        }
    }

    /**
     * Takes the length of a short first page followed by another one as the page size; tells
     * whether it changed.
     */
    private function learn(ReleasePage $page): bool
    {
        $size = \count($page->releases) + $page->skipped;
        if ($size < 1 || $size * 2 > $this->perPage) {
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
