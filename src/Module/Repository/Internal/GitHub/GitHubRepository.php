<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\Repository\Internal\GitHub;

use Internal\Destroy\Destroyable;
use Internal\DLoad\Module\Repository\Collection\ReleasesCollection;
use Internal\DLoad\Module\Repository\Exception\RateLimitException;
use Internal\DLoad\Module\Repository\Internal\GitHub\Api\RepositoryApi;
use Internal\DLoad\Module\Repository\Repository;
use Internal\DLoad\Service\Logger;

/**
 * GitHub Repository class representing a GitHub repository.
 *
 * @internal
 * @psalm-internal Internal\DLoad\Module\Repository\Internal\GitHub
 */
final class GitHubRepository implements Repository, Destroyable
{
    private ?ReleasesCollection $releases = null;

    /**
     * Package name in format "owner/repository"
     *
     * @var non-empty-string
     */
    private readonly string $name;

    /**
     * @param non-empty-string $org
     * @param non-empty-string $repo
     */
    public function __construct(
        private readonly RepositoryApi $api,
        string $org,
        string $repo,
        private readonly Logger $logger,
    ) {
        $this->name = $org . '/' . $repo;
    }

    /**
     * Returns a lazily loaded collection of repository releases.
     * Pages are loaded only when needed during iteration or filtering.
     */
    public function getReleases(): ReleasesCollection
    {
        if ($this->releases !== null) {
            return $this->releases;
        }

        // Create a generator function for lazy loading release pages
        $pageLoader = function (): \Generator {
            /** @var \Internal\DLoad\Module\Repository\Internal\Paginator<Api\Response\ReleaseInfo>|null $page */
            $page = null;
            $anyPageLoaded = false;

            do {
                try {
                    // to avoid first eager loading because of generator
                    yield [];

                    # Asking the paginator for the next page IS the request for it: building a new
                    # paginator per page instead would send every page but the first one twice.
                    $page = $page === null ? $this->api->getReleases() : $page->getNextPage();

                    if ($page === null) {
                        return;
                    }

                    $toYield = [];
                    foreach ($page->getPageItems() as $releaseDTO) {
                        try {
                            $toYield[] = GitHubRelease::fromDTO($this->api, $this, $releaseDTO);
                        } catch (\Throwable $e) {
                            $this->logger->exception($e, important: false);
                            // Skip invalid releases
                            continue;
                        }
                    }
                    yield $toYield;
                    $anyPageLoaded = true;
                } catch (\Throwable $e) {
                    # The first page is mandatory: when it fails, there is nothing to download and the reason
                    # (invalid token, rate limit, missing repository, etc.) must reach the user.
                    $anyPageLoaded or throw $e;

                    # A rate limit leaves the release list incomplete: hiding it would produce a report
                    # that claims the repository has nothing more, so it must reach the user as well.
                    $e instanceof RateLimitException and throw $e;

                    # Already loaded releases are enough to continue, so a failure of a subsequent page
                    # only stops the pagination.
                    $this->logger->exception($e, important: false);
                    return;
                }
            } while (true);
        };

        // Create paginator
        $paginator = \Internal\DLoad\Module\Repository\Internal\Paginator::createFromGenerator($pageLoader(), null);

        // Create a collection with the paginator
        $this->releases = ReleasesCollection::create($paginator);

        return $this->releases;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function destroy(): void
    {
        $this->releases === null or $this->releases->map(
            static fn(object $release) => $release instanceof Destroyable and $release->destroy(),
        );

        unset($this->releases);
    }
}
