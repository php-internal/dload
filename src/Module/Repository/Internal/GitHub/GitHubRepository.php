<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\Repository\Internal\GitHub;

use Internal\DLoad\Module\Repository\Collection\ReleasesCollection;
use Internal\DLoad\Module\Repository\Internal\GitHub\Api\RepositoryApi;
use Internal\DLoad\Module\Repository\Repository;
use Internal\DLoad\Service\Destroyable;

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
            $page = 0;

            do {
                try {
                    // to avoid first eager loading because of generator
                    yield [];

                    $paginator = $this->api->getReleases(++$page);
                    $releases = $paginator->getPageItems();

                    $toYield = [];
                    foreach ($releases as $releaseDTO) {
                        try {
                            $toYield[] = GitHubRelease::fromDTO($this->api, $this, $releaseDTO);
                        } catch (\Throwable) {
                            // Skip invalid releases
                            continue;
                        }
                    }
                    yield $toYield;

                    // Check if there are more pages by getting next page
                    $hasMorePages = $paginator->getNextPage() !== null;
                } catch (\Throwable) {
                    return;
                }
            } while ($hasMorePages);
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
