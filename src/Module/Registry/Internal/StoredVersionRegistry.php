<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\Registry\Internal;

use Internal\DLoad\Module\Registry\Record\ReleasePage;
use Internal\DLoad\Module\Registry\Record\ReleaseRecord;
use Internal\DLoad\Module\Registry\Record\RepositoryRecord;
use Internal\DLoad\Module\Registry\RegistryStorage;
use Internal\DLoad\Module\Registry\ReleaseSource;
use Internal\DLoad\Module\Registry\RepositoryId;
use Internal\DLoad\Module\Registry\VersionRegistry;
use Internal\DLoad\Module\Repository\Exception\RateLimitException;
use Internal\DLoad\Module\Repository\Exception\RepositoryException;
use Internal\DLoad\Service\Logger;

/**
 * Registry backed by a storage, with a freshness window on the last check.
 *
 * Listing a repository goes through three steps, each of them costing requests only when needed:
 *
 * 1. **Check.** When the last check is older than the TTL (or a refresh is forced), the newest
 *    pages are fetched until a page contains a release that is already stored. Usually that is
 *    one request. The first page is always taken from the source, so releases whose assets were
 *    attached after the check are updated too.
 * 2. **Serve.** The stored releases are yielded segment by segment without any request.
 * 3. **Extend.** When the consumer runs past the stored releases and the listing is not known
 *    to be complete, older pages are fetched one by one and appended to the record. After a
 *    check, the stored releases below the ones it confirmed are first found to be where they
 *    were, as a deletion among them would shift the listing.
 *
 * A check that fails while releases are stored falls back to the stored ones: an outage or a rate
 * limit should not break what worked a minute ago. A rate limit is reported once per run in plain
 * sight, as the stored list may be missing newer releases until the limit resets.
 *
 * @internal
 * @psalm-internal Internal\DLoad
 */
final class StoredVersionRegistry implements VersionRegistry
{
    /** @var \Closure(): int */
    private readonly \Closure $clock;

    private bool $rateLimitReported = false;

    /**
     * @param int<0, max> $ttl Seconds the last check stays valid.
     * @param bool $refresh Ignore the TTL and check the source for every repository once.
     * @param null|\Closure(): int $clock Current unix time; defaults to `time()`.
     */
    public function __construct(
        private readonly RegistryStorage $storage,
        private readonly int $ttl,
        private readonly Logger $logger,
        private readonly bool $refresh = false,
        ?\Closure $clock = null,
    ) {
        $this->clock = $clock ?? static fn(): int => \time();
    }

    public function releases(RepositoryId $id, ReleaseSource $source): \Generator
    {
        $record = $this->storage->load($id) ?? RepositoryRecord::empty($id);

        // Within the TTL the stored list is taken as it is, head and tail alike
        $verified = $record->count();
        $unreadable = 0;
        if ($this->refresh || $record->isStale(($this->clock)(), $this->ttl)) {
            [$record, $verified, $unreadable] = $this->check($record, $source);
        } else {
            $this->logger->debug('Releases of `%s` are served from the version registry.', (string) $id);
        }

        foreach ($record->pages() as $page) {
            yield ReleaseRecord::visible($page);
        }

        if ($record->complete) {
            return;
        }

        // Older releases are loaded only when the consumer actually needs them
        yield from $this->extend($record, $source, $verified, $unreadable);
    }

    public function attach(RepositoryId $id, string $software): void
    {
        $record = $this->storage->load($id) ?? RepositoryRecord::empty($id);
        $updated = $record->withSoftware($software);

        $updated === $record or $this->persist($updated);
    }

    public function forget(RepositoryId $id, string $tag): void
    {
        $record = $this->storage->load($id);
        if ($record === null || !$record->has($tag)) {
            return;
        }

        $this->logger->debug('Release `%s` of `%s` is gone: dropped from the version registry.', $tag, (string) $id);

        // Without the last check the next listing asks the source again
        $this->persist($record->withoutRelease($tag)->withoutCheck());
    }

    /**
     * @param list<ReleaseRecord> $page
     */
    private static function hasKnown(RepositoryRecord $record, array $page): bool
    {
        foreach ($page as $release) {
            if ($record->has($release->tag)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Fetches the releases published since the last check and stores the result.
     *
     * Besides the record, tells how many of its leading releases the check has seen in place,
     * as the stored releases beyond them may have shifted since they were stored, and how many
     * listing positions among the pages it fetched hold a release the record cannot account
     * for, as an unreadable one. A check that leaves the record untouched confirms nothing.
     *
     * @return array{RepositoryRecord, int<0, max>, int<0, max>}
     * @throws RepositoryException When the source fails and nothing is stored to fall back on.
     */
    private function check(RepositoryRecord $record, ReleaseSource $source): array
    {
        try {
            $fetched = [];
            $unreadable = 0;
            $complete = $record->complete;

            foreach ($source->pages() as $page) {
                $unreadable += $page->skipped;

                // A release the source could not read is missing from the page exactly like a
                // deleted one, and `withHead()` would drop it; the stored list stays untouched
                // and the next run checks again
                if ($page->skipped > 0 && $record->count() > 0) {
                    $this->logger->debug(
                        'The listing of `%s` has %d unreadable release(s); the stored %d are kept as they are.',
                        (string) $record->id,
                        $page->skipped,
                        $record->count(),
                    );

                    return [$record, 0, $unreadable];
                }

                $fetched = [...$fetched, ...$page->releases];

                // The listing ended during the check: everything is known now
                $page->last and $complete = true;

                // Reaching a known release means everything newer has been fetched. A record
                // without releases cannot hit one, so its check is the first page only.
                if ($page->last || $record->count() === 0 || self::hasKnown($record, $page->releases)) {
                    break;
                }
            }

            // A listing without a single page has no releases at all
            $fetched === [] and $complete = true;

            $record = $this->persist(
                $record
                    ->withHead($fetched)
                    ->withComplete($complete)
                    ->withCheckedAt(($this->clock)()),
            );

            return [$record, \count($fetched), 0];
        } catch (RepositoryException $e) {
            $record->count() > 0 or throw $e;

            $this->report($e, $record);

            return [$record, 0, 0];
        }
    }

    /**
     * Loads the releases older than the stored ones page by page, persisting every page.
     *
     * The source pages by position, and a stored release deleted upstream below the verified
     * ones moves every older release up by one. So past them the loading starts one release
     * earlier and expects the last stored release there; when another one shows up, or no page
     * at all, the unverified tail is dropped and loaded again, without repeating to the consumer
     * what it was served from the stored list. The dropped tail is written only along with the
     * first page that replaces it, so a source failing right there leaves the record as it was.
     *
     * The extra release lies on the page needed anyway when the stored list ends inside a page.
     * A list ending on a page boundary, as one made of fully loaded pages does, costs one more
     * request for the page that ends with the release.
     *
     * @param int<0, max> $verified Number of leading stored releases known to be in place.
     * @param int<0, max> $unreadable Listing positions above the stored tail that hold no stored
     *        release, so the last stored release sits that many positions below the stored count.
     * @return \Generator<int, list<ReleaseRecord>, mixed, void>
     * @throws RepositoryException
     */
    private function extend(RepositoryRecord $record, ReleaseSource $source, int $verified, int $unreadable): \Generator
    {
        $served = $record;
        $anchor = $verified < $record->count() ? $record->lastTag() : null;
        /** @var int<0, max> $offset */
        $offset = $record->count() + $unreadable - ($anchor === null ? 0 : 1);

        $pages = $source->pages($offset);
        $page = $pages->current();

        while (true) {
            if ($anchor !== null) {
                if ($page === null || ($page->releases[0] ?? null)?->tag !== $anchor) {
                    $this->logger->debug(
                        'The stored tail of `%s` has shifted: %d release(s) are kept, the rest is loaded again.',
                        (string) $record->id,
                        $verified,
                    );

                    $record = $record->withoutTail($verified);
                    $anchor = null;
                    $pages = $source->pages($record->count());
                    $page = $pages->current();

                    continue;
                }

                $anchor = null;
                $page = new ReleasePage(\array_slice($page->releases, 1), $page->last, $page->skipped);
            }

            if ($page === null) {
                break;
            }

            $new = \array_values(\array_filter(
                $page->releases,
                static fn(ReleaseRecord $release): bool => !$record->has($release->tag),
            ));

            $record = $this->persist($record->withTail($new)->withComplete($page->last));

            $visible = ReleaseRecord::visible(\array_values(\array_filter(
                $new,
                static fn(ReleaseRecord $release): bool => !$served->has($release->tag),
            )));
            $visible === [] or yield $visible;

            $pages->next();
            $page = $pages->current();
        }

        $record->complete or $this->persist($record->withComplete(true));
    }

    /**
     * Tells why the check was skipped. A rate limit is shown to the user once per run: the stored
     * list still works, but it may lack newer releases until the limit resets. Anything else is
     * an ordinary transient failure and stays in the debug output.
     */
    private function report(RepositoryException $e, RepositoryRecord $record): void
    {
        $this->logger->exception($e, important: false);

        if ($e instanceof RateLimitException && !$this->rateLimitReported) {
            $this->rateLimitReported = true;
            $this->logger->error(
                'The API rate limit prevents checking `%s` for new releases; %d stored release(s) are used, newer ones may be missing. %s',
                (string) $record->id,
                $record->count(),
                $e->getMessage(),
            );

            return;
        }

        $this->logger->debug(
            'Failed to check `%s` for new releases, %d stored release(s) are used instead.',
            (string) $record->id,
            $record->count(),
        );
    }

    /**
     * Stores the record; a storage failure is reported and swallowed.
     *
     * Returns the record as stored, so later writes do not repeat the segments already written.
     */
    private function persist(RepositoryRecord $record): RepositoryRecord
    {
        try {
            $this->storage->save($record);
        } catch (\Throwable $e) {
            $this->logger->exception($e, important: false);
        }

        return $record->persisted();
    }
}
