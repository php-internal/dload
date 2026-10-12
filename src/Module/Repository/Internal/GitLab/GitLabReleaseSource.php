<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\Repository\Internal\GitLab;

use Internal\DLoad\Module\Registry\Record\ReleasePage;
use Internal\DLoad\Module\Registry\ReleaseSource;
use Internal\DLoad\Module\Repository\Internal\GitLab\Api\RepositoryApi;

/**
 * Adapts the GitLab releases API to the version registry.
 *
 * Translates a release offset into the API page and the number of releases to drop from it.
 * The page size is the one asked for until a response shows that the server puts fewer
 * releases on a page, as a mirror or a server that caps `per_page` does.
 *
 * @internal
 * @psalm-internal Internal\DLoad\Module\Repository\Internal\GitLab
 */
final class GitLabReleaseSource implements ReleaseSource
{
    /** @var int<1, max> */
    private int $perPage = RepositoryApi::RELEASES_PER_PAGE;

    public function __construct(
        private readonly RepositoryApi $api,
    ) {}

    public function pages(int $offset = 0): \Generator
    {
        $first = $this->pageOf($offset);
        $pages = $this->api->releasePages($first);
        $page = $pages->current();

        // A page followed by another one holds exactly as many releases as the server serves per
        // page; the offset may then lie on a later page than the one fetched
        if ($page !== null && !$page->last && $this->learn($page) && $this->pageOf($offset) !== $first) {
            $pages = $this->api->releasePages($this->pageOf($offset));
            $page = $pages->current();
        }

        // The generator is walked by hand: one that ended before its first page is closed, and
        // `foreach` cannot start it over
        $skip = $offset % $this->perPage;
        while ($page !== null) {
            yield $skip === 0 ? $page : new ReleasePage(\array_slice($page->releases, $skip), $page->last, $page->skipped);
            $skip = 0;

            $pages->next();
            $page = $pages->current();
        }
    }

    /**
     * Takes the size of a full page as the page size; tells whether it changed.
     */
    private function learn(ReleasePage $page): bool
    {
        $size = \count($page->releases) + $page->skipped;
        if ($size < 1 || $size === $this->perPage) {
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
