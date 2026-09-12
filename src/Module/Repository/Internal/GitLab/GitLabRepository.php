<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\Repository\Internal\GitLab;

use Internal\Destroy\Destroyable;
use Internal\DLoad\Module\Registry\RepositoryId;
use Internal\DLoad\Module\Registry\VersionRegistry;
use Internal\DLoad\Module\Repository\Collection\ReleasesCollection;
use Internal\DLoad\Module\Repository\Exception\RateLimitException;
use Internal\DLoad\Module\Repository\Internal\GitLab\Api\RepositoryApi;
use Internal\DLoad\Module\Repository\Internal\Paginator;
use Internal\DLoad\Module\Repository\Repository;
use Internal\DLoad\Service\Logger;

/**
 * GitLab Repository class representing a GitLab repository.
 *
 * @internal
 * @psalm-internal Internal\DLoad\Module\Repository\Internal\GitLab
 */
final class GitLabRepository implements Repository, Destroyable
{
    /** Repository type identifier in the version registry. */
    public const TYPE = 'gitlab';

    private ?ReleasesCollection $releases = null;

    /**
     * Package name in format "owner/repository"
     *
     * @var non-empty-string
     */
    private readonly string $name;

    /**
     * @param non-empty-string $projectPath
     */
    public function __construct(
        private readonly RepositoryApi $api,
        string $projectPath,
        private readonly Logger $logger,
        private readonly VersionRegistry $registry,
    ) {
        $this->name = $projectPath;
    }

    /**
     * Returns a lazily loaded collection of repository releases.
     *
     * Releases come from the version registry, which serves stored ones without a request and
     * asks the API only for what it does not know yet. Pages are loaded only when needed during
     * iteration or filtering.
     */
    public function getReleases(): ReleasesCollection
    {
        if ($this->releases !== null) {
            return $this->releases;
        }

        // Create a generator function for lazy loading release pages
        $pageLoader = function (): \Generator {
            // to avoid first eager loading because of generator
            yield [];

            $pages = $this->registry->releases(
                new RepositoryId(self::TYPE, $this->name),
                new GitLabReleaseSource($this->api),
            );
            $anyPageLoaded = false;

            while (true) {
                try {
                    // Advancing the generator is what requests the next page
                    $anyPageLoaded ? $pages->next() : $pages->rewind();

                    if (!$pages->valid()) {
                        return;
                    }

                    $toYield = [];
                    foreach ($pages->current() as $record) {
                        try {
                            $toYield[] = GitLabRelease::fromRecord($this->api, $this, $record);
                        } catch (\Throwable) {
                            // Skip invalid releases
                            continue;
                        }
                    }

                    $anyPageLoaded = true;
                    yield $toYield;
                } catch (\Throwable $e) {
                    # The first page is mandatory: when it fails, there is nothing to download and the reason
                    # (invalid token, rate limit, missing project, etc.) must reach the user.
                    $anyPageLoaded or throw $e;

                    # A rate limit leaves the release list incomplete: hiding it would produce a report
                    # that claims the repository has nothing more, so it must reach the user as well.
                    $e instanceof RateLimitException and throw $e;

                    # Already loaded releases are enough to continue, so a failure of a subsequent page
                    # only stops the pagination.
                    $this->logger->exception($e, important: false);
                    return;
                }
            }
        };

        // Create paginator
        $paginator = Paginator::createFromGenerator($pageLoader(), null);

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
        // Only what was loaded is released: iterating the collection would request the remaining pages
        foreach ($this->releases?->loaded() ?? [] as $release) {
            $release instanceof Destroyable and $release->destroy();
        }

        unset($this->releases);
    }
}
