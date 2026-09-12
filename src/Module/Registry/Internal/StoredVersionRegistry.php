<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\Registry\Internal;

use Internal\DLoad\Module\Registry\Record\ReleaseRecord;
use Internal\DLoad\Module\Registry\Record\RepositoryRecord;
use Internal\DLoad\Module\Registry\RegistryStorage;
use Internal\DLoad\Module\Registry\ReleaseSource;
use Internal\DLoad\Module\Registry\RepositoryId;
use Internal\DLoad\Module\Registry\VersionRegistry;
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
 * 2. **Serve.** The stored releases are yielded without any request.
 * 3. **Extend.** When the consumer runs past the stored releases and the listing is not known
 *    to be complete, older pages are fetched one by one and appended to the record.
 *
 * A check that fails while releases are stored falls back to the stored ones: an outage or a rate
 * limit should not break what worked a minute ago.
 *
 * @internal
 * @psalm-internal Internal\DLoad
 */
final class StoredVersionRegistry implements VersionRegistry
{
    /** @var \Closure(): int */
    private readonly \Closure $clock;

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

        if ($this->refresh || $record->isStale(($this->clock)(), $this->ttl)) {
            $record = $this->check($record, $source);
        } else {
            $this->logger->debug('Releases of `%s` are served from the version registry.', (string) $id);
        }

        $stored = $record->releases();
        $stored === [] or yield $stored;

        if ($record->complete) {
            return;
        }

        // Older releases are loaded only when the consumer actually needs them
        yield from $this->extend($record, $source);
    }

    public function attach(string $software, RepositoryId $id): void
    {
        $record = $this->storage->load($id) ?? RepositoryRecord::empty($id);
        $updated = $record->withSoftware($software);

        $updated === $record or $this->persist($updated);
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
     * @throws RepositoryException When the source fails and nothing is stored to fall back on.
     */
    private function check(RepositoryRecord $record, ReleaseSource $source): RepositoryRecord
    {
        try {
            $fetched = [];
            $complete = $record->complete;

            foreach ($source->pages() as $page) {
                $fetched = [...$fetched, ...$page->releases];

                // The listing ended during the check: everything is known now
                $page->last and $complete = true;

                // Reaching a known release means everything newer has been fetched. A record
                // without releases cannot hit one, so its check is the first page only.
                if ($page->last || $record->count() === 0 || self::hasKnown($record, $page->releases)) {
                    break;
                }
            }

            $updated = $record
                ->withHead($fetched)
                ->withComplete($complete)
                ->withCheckedAt(($this->clock)());

            $this->persist($updated);

            return $updated;
        } catch (RepositoryException $e) {
            $record->count() > 0 or throw $e;

            $this->logger->exception($e, important: false);
            $this->logger->info(
                'Failed to check `%s` for new releases, %d stored release(s) are used instead.',
                (string) $record->id,
                $record->count(),
            );

            return $record;
        }
    }

    /**
     * Loads the releases older than the stored ones page by page, persisting every page.
     *
     * @return \Generator<int, list<ReleaseRecord>, mixed, void>
     * @throws RepositoryException
     */
    private function extend(RepositoryRecord $record, ReleaseSource $source): \Generator
    {
        foreach ($source->pages($record->count()) as $page) {
            $new = \array_values(\array_filter(
                $page->releases,
                static fn(ReleaseRecord $release): bool => !$record->has($release->tag),
            ));

            $record = $record->withTail($new)->withComplete($page->last);
            $this->persist($record);

            $new === [] or yield $new;
        }

        $record->complete or $this->persist($record->withComplete(true));
    }

    /**
     * Stores the record; a storage failure is reported and swallowed.
     */
    private function persist(RepositoryRecord $record): void
    {
        try {
            $this->storage->save($record);
        } catch (\Throwable $e) {
            $this->logger->exception($e, important: false);
        }
    }
}
