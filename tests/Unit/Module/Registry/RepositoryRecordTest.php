<?php

declare(strict_types=1);

namespace Internal\DLoad\Tests\Unit\Module\Registry;

use Internal\DLoad\Module\Registry\Record\AssetRecord;
use Internal\DLoad\Module\Registry\Record\ReleaseRecord;
use Internal\DLoad\Module\Registry\Record\ReleaseSegment;
use Internal\DLoad\Module\Registry\Record\RepositoryRecord;
use Internal\DLoad\Module\Registry\RepositoryId;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Test;

#[Covers(RepositoryRecord::class)]
#[Covers(ReleaseSegment::class)]
#[Covers(ReleaseRecord::class)]
#[Covers(AssetRecord::class)]
#[Covers(RepositoryId::class)]
final class RepositoryRecordTest
{
    #[Test]
    public function headReplacesKnownReleasesAndKeepsNewestFirst(): void
    {
        $record = self::record(['v2' => 'old v2', 'v1' => 'v1']);

        $updated = $record->withHead([new ReleaseRecord('v3', 'v3'), new ReleaseRecord('v2', 'new v2')]);

        Assert::same(self::tags($updated), ['v3', 'v2', 'v1']);
        Assert::same($updated->releases()[1]->name, 'new v2');
    }

    #[Test]
    public function headDropsStoredReleasesMissingFromTheFetchedSpan(): void
    {
        // `v3` was deleted upstream: the fresh head reaches `v2`, and `v3` is not in it
        $record = self::record(['v4', 'v3', 'v2', 'v1']);

        $updated = $record->withHead(self::releases(['v5', 'v4', 'v2']));

        Assert::same(self::tags($updated), ['v5', 'v4', 'v2', 'v1']);
        Assert::same($updated->count(), 4);
    }

    #[Test]
    public function headDropsTheStoredReleaseRightAfterTheOnlyOneItReaches(): void
    {
        // `v5` was deleted: the fresh page reads `v6, v4`, so `v5` no longer follows `v6`
        $record = self::record(['v6', 'v5']);

        $updated = $record->withHead(self::releases(['v6', 'v4']));

        Assert::same(self::tags($updated), ['v6', 'v4']);
    }

    #[Test]
    public function headReachingNoStoredReleaseIsTheWholeListing(): void
    {
        $updated = self::record(['v1'])->withHead(self::releases(['v2']));

        Assert::same(self::tags($updated), ['v2']);
    }

    #[Test]
    public function headKeepsUntouchedSegmentsAsTheyAre(): void
    {
        // Three full segments; the head reaches the first one only
        $record = self::record(self::range(300, 1));
        $untouched = \array_slice($record->segments, 1);

        $updated = $record->withHead(self::releases(['v301', 'v300', 'v299']));

        Assert::same($updated->count(), 301);
        Assert::same(self::sizes($updated), [1, 100, 100, 100]);
        Assert::same(\array_slice($updated->segments, -2), $untouched);
        Assert::false($updated->segments[3]->dirty);
        Assert::true($updated->segments[0]->dirty);
        Assert::same($updated->segments[0]->tags, ['v301']);
    }

    #[Test]
    public function partialSegmentSitsAtTheHeadAndGrowsWithEveryCheck(): void
    {
        $record = self::record(self::range(150, 1));
        Assert::same(self::sizes($record), [50, 100]);

        // New releases join the head segment; the full one behind it is untouched
        $updated = $record->withHead(self::releases(['v152', 'v151', 'v150']));
        Assert::same(self::sizes($updated), [52, 100]);
        Assert::false($updated->segments[1]->dirty);
        Assert::same(\array_slice(self::tags($updated), 0, 4), ['v152', 'v151', 'v150', 'v149']);
    }

    #[Test]
    public function shortHeadJoinsTheNextSegmentWhileBothFitIntoOne(): void
    {
        $record = self::record(self::range(101, 1));
        Assert::same(self::sizes($record), [1, 100]);

        // `v101` opens its segment, so nothing is split; the single new release joins it
        $updated = $record->withHead(self::releases(['v102', 'v101']));

        Assert::same(self::sizes($updated), [2, 100]);
        Assert::false($updated->segments[1]->dirty);
        Assert::same($updated->segments[0]->tags, ['v102', 'v101']);
    }

    #[Test]
    public function tailFillsTheLastSegmentBeforeStartingANewOne(): void
    {
        $record = self::record(self::range(150, 1));
        $older = \array_map(static fn(int $i): string => 'o' . $i, \range(1, 70));

        // The last segment is full: the older releases open a new one
        $extended = $record->withTail(self::releases(\array_slice($older, 0, 62)))->persisted();
        Assert::same(self::sizes($extended), [50, 100, 62]);
        Assert::same(self::keys($extended), ['0001', '0002', '0003']);

        // The next page fills that segment up under the same key and starts another
        $more = $extended->withTail(self::releases(\array_slice($older, 62)))->withTail(self::releases(self::range(0, -35)));
        Assert::same(self::sizes($more), [50, 100, 100, 6]);
        Assert::same(self::keys($more), ['0001', '0002', '0003', '0004']);
        Assert::false($more->segments[1]->dirty);
        Assert::true($more->segments[2]->dirty);
        Assert::same($more->segments[2]->tags[99], 'v-29');
    }

    #[Test]
    public function tailIgnoresKnownReleases(): void
    {
        $record = self::record(['v2']);

        $updated = $record->withTail([new ReleaseRecord('v2', 'dup'), new ReleaseRecord('v1', 'v1')]);

        Assert::same(self::tags($updated), ['v2', 'v1']);
        Assert::same($updated->releases()[0]->name, 'v2');
        Assert::same($record->withTail([new ReleaseRecord('v2', 'dup')]), $record);
    }

    #[Test]
    public function segmentKeysAreNeverReused(): void
    {
        $record = self::record(self::range(200, 1));
        Assert::same(self::keys($record), ['0001', '0002']);

        // The head repacks the first segment under fresh keys; the old key is gone
        // `v200` was deleted: the first segment is repacked under a fresh key, the old key is gone
        $updated = $record->withHead(self::releases(['v201', 'v199']));
        Assert::same(self::keys($updated), ['0003', '0002']);
        Assert::same(self::sizes($updated), [100, 100]);

        // The tail appends after the greatest key ever used
        $extended = $updated->withTail(self::releases(['v0']));
        Assert::same(self::keys($extended), ['0003', '0002', '0004']);
    }

    #[Test]
    public function releaseCanBeDroppedAndTheCheckForgotten(): void
    {
        $record = (new RepositoryRecord(self::id(), checkedAt: 1_000))->withHead(self::releases(['v2', 'v1']));

        $dropped = $record->withoutRelease('v2');

        Assert::same(self::tags($dropped), ['v1']);
        Assert::same($dropped->checkedAt, 1_000);
        Assert::same($record->withoutRelease('v9'), $record);

        $stale = $dropped->withoutCheck();
        Assert::null($stale->checkedAt);
        Assert::same(self::tags($stale), ['v1']);
        Assert::true($stale->isStale(1_000, 600));
    }

    #[Test]
    public function droppingTheLastReleaseOfASegmentDropsTheSegment(): void
    {
        $record = self::record(self::range(101, 1));
        Assert::same(self::sizes($record), [1, 100]);

        $updated = $record->withoutRelease('v101');

        Assert::same(self::sizes($updated), [100]);
        Assert::false($updated->has('v101'));
        Assert::false($updated->segments[0]->dirty);
    }

    #[Test]
    public function stalenessDependsOnTheLastCheck(): void
    {
        $never = RepositoryRecord::empty(self::id());
        $checked = $never->withCheckedAt(1_000);

        Assert::true($never->isStale(1_000, 600));
        Assert::false($checked->isStale(1_600, 600));
        Assert::true($checked->isStale(1_601, 600));
    }

    #[Test]
    public function softwareIsAttachedOnce(): void
    {
        $record = RepositoryRecord::empty(self::id())->withSoftware('rr');

        Assert::same($record->withSoftware('rr'), $record);
        Assert::same($record->withSoftware('temporal')->software, ['rr', 'temporal']);
    }

    #[Test]
    public function survivesTheArrayRoundTrip(): void
    {
        $record = (new RepositoryRecord(id: self::id(), checkedAt: 1_000, complete: true, software: ['rr']))
            ->withHead([
                new ReleaseRecord(
                    tag: 'v2.0.0',
                    name: 'Release 2',
                    publishedAt: new \DateTimeImmutable('2024-01-02T03:04:05+00:00'),
                    prerelease: true,
                    assets: [new AssetRecord('rr-linux-amd64.tar.gz', 'https://x/rr.tar.gz', 42, 'application/gzip', 'sha256:9f86d081884c7d659a2feaa0c55ad015a3bf4f1b2b0b822cd15d6c15b0f00a08')],
                ),
                new ReleaseRecord('v1.0.0', 'v1.0.0'),
            ]);

        // The index travels as JSON; the segments are handed back through the loader
        $segments = [];
        foreach ($record->segments as $segment) {
            $segments[$segment->key] = \json_decode(\json_encode(\array_map(
                static fn(ReleaseRecord $release): array => $release->toArray(),
                $segment->releases(),
            )), true);
        }
        $restored = RepositoryRecord::fromArray(
            \json_decode(\json_encode($record->toArray()), true),
            static fn(string $key): array => \array_map(ReleaseRecord::fromArray(...), $segments[$key]),
        );

        Assert::same($restored->toArray(), $record->toArray());
        Assert::true($restored->id->equals(self::id()));
        Assert::same($restored->count(), 2);
        Assert::false($restored->segments[0]->dirty);
        Assert::same($restored->releases()[0]->assets[0]->size, 42);
        Assert::same($restored->releases()[0]->assets[0]->digest, 'sha256:9f86d081884c7d659a2feaa0c55ad015a3bf4f1b2b0b822cd15d6c15b0f00a08');
        Assert::same($restored->releases()[0]->publishedAt?->format(\DATE_ATOM), '2024-01-02T03:04:05+00:00');
        Assert::null($restored->releases()[1]->publishedAt);
        Assert::null(AssetRecord::fromArray(['name' => 'x', 'uri' => 'https://x', 'digest' => ''])->digest);
    }

    #[Test]
    public function segmentsAreReadOnlyWhenReached(): void
    {
        $loaded = [];
        $old = self::range(100, 1);
        $record = RepositoryRecord::fromArray(
            [
                'version' => RepositoryRecord::FORMAT_VERSION,
                'repository' => ['type' => 'github', 'uri' => 'owner/repo'],
                'segments' => [['key' => 'a', 'tags' => ['v102', 'v101']], ['key' => 'b', 'tags' => $old]],
            ],
            static function (string $key) use (&$loaded, $old): array {
                $loaded[] = $key;

                return self::releases($key === 'a' ? ['v102', 'v101'] : $old);
            },
        );

        Assert::same($record->count(), 102);
        Assert::true($record->has('v1'));
        Assert::same($loaded, []);

        $record->pages()->current();
        Assert::same($loaded, ['a']);

        // The head touches the first segment only; the full one behind it is neither read nor rewritten
        $updated = $record->withHead(self::releases(['v103', 'v102']));
        Assert::same($loaded, ['a']);
        Assert::same(self::sizes($updated), [3, 100]);
        Assert::false($updated->segments[1]->dirty);
    }

    #[Test]
    public function persistedRecordHasNoDirtySegments(): void
    {
        $record = RepositoryRecord::empty(self::id())->withHead(self::releases(['v1']));
        Assert::true($record->segments[0]->dirty);

        $persisted = $record->persisted();

        Assert::false($persisted->segments[0]->dirty);
        Assert::same(self::tags($persisted), ['v1']);
        Assert::same($persisted->persisted()->segments, $persisted->segments);
    }

    #[Test]
    public function rejectsUnknownFormatVersion(): void
    {
        try {
            RepositoryRecord::fromArray(['version' => 99, 'repository' => ['type' => 'github', 'uri' => 'a/b']], static fn(): array => []);
            Assert::fail('An unknown format version must be rejected.');
        } catch (\InvalidArgumentException $e) {
            Assert::same($e->getMessage(), 'Unsupported repository record format.');
        }
    }

    #[Test]
    public function skipsBrokenReleasesButRequiresATag(): void
    {
        try {
            ReleaseRecord::fromArray(['name' => 'no tag']);
            Assert::fail('A release without a tag must be rejected.');
        } catch (\InvalidArgumentException $e) {
            Assert::same($e->getMessage(), 'Release record requires a non-empty `tag`.');
        }

        // A broken asset makes the whole release unusable rather than silently dropping the asset
        try {
            ReleaseRecord::fromArray(['tag' => 'v1', 'assets' => [['name' => 'x']]]);
            Assert::fail('An asset without a URI must be rejected.');
        } catch (\InvalidArgumentException) {
        }

        $release = ReleaseRecord::fromArray(['tag' => 'v1', 'assets' => [['name' => 'ok', 'uri' => 'https://x']]]);
        Assert::same($release->name, 'v1');
        Assert::count($release->assets, 1);
    }

    #[Test]
    public function repositoryIdIsNormalized(): void
    {
        $id = new RepositoryId('GitHub', '/Owner/Repo/');

        Assert::same((string) $id, 'github:owner/repo');
        Assert::true($id->equals(new RepositoryId('github', 'owner/repo')));

        try {
            new RepositoryId('github', '/');
            Assert::fail('A URI without a path must be rejected.');
        } catch (\InvalidArgumentException) {
        }
    }

    private static function id(): RepositoryId
    {
        return new RepositoryId('github', 'owner/repo');
    }

    /**
     * Record holding the given releases as freshly packed segments.
     *
     * @param array<array-key, non-empty-string> $tags Newest first; a string key is the tag and the value its name.
     */
    private static function record(array $tags): RepositoryRecord
    {
        $releases = [];
        foreach ($tags as $key => $value) {
            $releases[] = \is_string($key) ? new ReleaseRecord($key, $value) : new ReleaseRecord($value, $value);
        }

        return RepositoryRecord::empty(self::id())->withHead($releases)->persisted();
    }

    /**
     * @param list<non-empty-string> $tags
     * @return list<ReleaseRecord>
     */
    private static function releases(array $tags): array
    {
        return \array_map(static fn(string $tag): ReleaseRecord => new ReleaseRecord($tag, $tag), $tags);
    }

    /**
     * @return list<non-empty-string> `v<from>` down to `v<to>`.
     */
    private static function range(int $from, int $to): array
    {
        return \array_map(static fn(int $i): string => 'v' . $i, \range($from, $to));
    }

    /**
     * @return list<non-empty-string>
     */
    private static function tags(RepositoryRecord $record): array
    {
        return \array_map(static fn(ReleaseRecord $release): string => $release->tag, $record->releases());
    }

    /**
     * @return list<int>
     */
    private static function sizes(RepositoryRecord $record): array
    {
        return \array_map(static fn(ReleaseSegment $segment): int => $segment->count(), $record->segments);
    }

    /**
     * @return list<non-empty-string>
     */
    private static function keys(RepositoryRecord $record): array
    {
        return \array_map(static fn(ReleaseSegment $segment): string => $segment->key, $record->segments);
    }
}
