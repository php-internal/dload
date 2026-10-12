<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\Registry\Internal;

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
 *    to be complete, older pages are fetched one by one and appended to the record, from the
 *    last stored release on.
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

        // Only a check can tell that the stored releases are still where they were listed
        $seen = false;
        if ($this->refresh || $record->isStale(($this->clock)(), $this->ttl)) {
            [$record, $seen] = $this->check($record, $source);
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
        yield from $this->extend($record, $source, $seen);
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
     * @return int<0, max>|null Index of the last stored release of the page, if any.
     */
    private static function lastKnown(RepositoryRecord $record, array $page): ?int
    {
        for ($index = \count($page) - 1; $index >= 0; --$index) {
            if ($record->has($page[$index]->tag)) {
                return $index;
            }
        }

        return null;
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
     * Also tells whether the check has seen every stored release where it is listed now.
     *
     * @return array{RepositoryRecord, bool}
     * @throws RepositoryException When the source fails and nothing is stored to fall back on.
     */
    private function check(RepositoryRecord $record, ReleaseSource $source): array
    {
        try {
            $fetched = [];
            $complete = $record->complete;

            foreach ($source->pages() as $page) {
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

                    return [$record, false];
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

            $tags = \array_map(static fn(ReleaseRecord $release): string => $release->tag, $fetched);

            return [$record, $record->count() <= \count(\array_unique($tags))];
        } catch (RepositoryException $e) {
            $record->count() > 0 or throw $e;

            $this->report($e, $record);

            return [$record, false];
        }
    }

    /**
     * Loads the releases older than the stored ones page by page, persisting every page.
     *
     * The stored count only hints at the listing position of the last stored release: a stored
     * release deleted upstream moves it up, a release the record misses above it moves it down.
     * So the loading starts with the page holding that position and goes on after the last
     * stored release found there. A page holding none sends the search up the listing; when it
     * reaches the top without one, the search goes down from the first page instead. The page
     * fetched first is the one needed anyway when the stored list ends inside a page; a list
     * ending on a page boundary costs one request more. A list the check has just seen whole is
     * where it was fetched, so its tail goes on right after it.
     *
     * A stored release deleted upstream stays stored until its download fails and it is
     * forgotten. A release the listing gained above the last stored one, such as an unreadable
     * one turned readable, is not loaded.
     *
     * @param bool $seen Whether the check has just seen every stored release where it is.
     * @return \Generator<int, list<ReleaseRecord>, mixed, void>
     * @throws RepositoryException
     */
    private function extend(RepositoryRecord $record, ReleaseSource $source, bool $seen): \Generator
    {
        $found = $seen || $record->count() === 0;
        $first = $pages = $source->pages($found ? $record->count() : \max(0, $record->count() - 1));
        // The search goes up while the pages it meets start ever higher, then down once
        $up = true;
        $above = \PHP_INT_MAX;

        while (($page = $pages->current()) !== null) {
            $at = self::lastKnown($record, $page->releases);
            if (!$found && $at === null) {
                if ($up && $page->offset > 0 && $page->offset < $above) {
                    $above = $page->offset;
                    $pages = $source->pages($page->offset - 1);
                    continue;
                }

                // Nothing stored up to the top: releases the record misses pushed the tail down
                $up and $pages = $first;
                $up = false;
                $pages->next();
                continue;
            }

            $found = true;
            $new = $at === null ? $page->releases : \array_slice($page->releases, $at + 1);

            $record = $this->persist($record->withTail($new)->withComplete($page->last));

            $visible = ReleaseRecord::visible($new);
            $visible === [] or yield $visible;

            $pages->next();
        }

        // A listing without any stored release is left for the next check to replace
        $found and !$record->complete and $this->persist($record->withComplete(true));
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
