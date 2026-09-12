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
 *
 * @internal
 * @psalm-internal Internal\DLoad\Module\Repository\Internal\GitLab
 */
final class GitLabReleaseSource implements ReleaseSource
{
    public function __construct(
        private readonly RepositoryApi $api,
    ) {}

    public function pages(int $offset = 0): \Generator
    {
        $skip = $offset % RepositoryApi::RELEASES_PER_PAGE;

        foreach ($this->api->releasePages(\intdiv($offset, RepositoryApi::RELEASES_PER_PAGE) + 1) as $page) {
            yield $skip === 0 ? $page : new ReleasePage(\array_slice($page->releases, $skip), $page->last);
            $skip = 0;
        }
    }
}
