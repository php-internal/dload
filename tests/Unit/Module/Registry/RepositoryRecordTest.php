<?php

declare(strict_types=1);

namespace Internal\DLoad\Tests\Unit\Module\Registry;

use Internal\DLoad\Module\Registry\Record\AssetRecord;
use Internal\DLoad\Module\Registry\Record\ReleaseRecord;
use Internal\DLoad\Module\Registry\Record\RepositoryRecord;
use Internal\DLoad\Module\Registry\RepositoryId;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Test;

#[Covers(RepositoryRecord::class)]
#[Covers(ReleaseRecord::class)]
#[Covers(AssetRecord::class)]
#[Covers(RepositoryId::class)]
final class RepositoryRecordTest
{
    #[Test]
    public function headReplacesKnownReleasesAndKeepsNewestFirst(): void
    {
        $record = new RepositoryRecord(self::id(), releases: [
            new ReleaseRecord('v2', 'old v2'),
            new ReleaseRecord('v1', 'v1'),
        ]);

        $updated = $record->withHead([new ReleaseRecord('v3', 'v3'), new ReleaseRecord('v2', 'new v2')]);

        Assert::same(self::tags($updated), ['v3', 'v2', 'v1']);
        Assert::same($updated->releases()[1]->name, 'new v2');
    }

    #[Test]
    public function tailIgnoresKnownReleases(): void
    {
        $record = new RepositoryRecord(self::id(), releases: [new ReleaseRecord('v2', 'v2')]);

        $updated = $record->withTail([new ReleaseRecord('v2', 'dup'), new ReleaseRecord('v1', 'v1')]);

        Assert::same(self::tags($updated), ['v2', 'v1']);
        Assert::same($updated->releases()[0]->name, 'v2');
    }

    #[Test]
    public function releaseCanBeDroppedAndTheCheckForgotten(): void
    {
        $record = new RepositoryRecord(self::id(), checkedAt: 1_000, releases: [
            new ReleaseRecord('v2', 'v2'),
            new ReleaseRecord('v1', 'v1'),
        ]);

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
        $record = new RepositoryRecord(
            id: self::id(),
            checkedAt: 1_000,
            complete: true,
            software: ['rr'],
            releases: [
                new ReleaseRecord(
                    tag: 'v2.0.0',
                    name: 'Release 2',
                    publishedAt: new \DateTimeImmutable('2024-01-02T03:04:05+00:00'),
                    prerelease: true,
                    assets: [new AssetRecord('rr-linux-amd64.tar.gz', 'https://x/rr.tar.gz', 42, 'application/gzip')],
                ),
                new ReleaseRecord('v1.0.0', 'v1.0.0'),
            ],
        );

        $restored = RepositoryRecord::fromArray(\json_decode(\json_encode($record->toArray()), true));

        Assert::same($restored->toArray(), $record->toArray());
        Assert::true($restored->id->equals(self::id()));
        Assert::same($restored->releases()[0]->assets[0]->size, 42);
        Assert::same($restored->releases()[0]->publishedAt?->format(\DATE_ATOM), '2024-01-02T03:04:05+00:00');
        Assert::null($restored->releases()[1]->publishedAt);
    }

    #[Test]
    public function rejectsUnknownFormatVersion(): void
    {
        try {
            RepositoryRecord::fromArray(['version' => 99, 'repository' => ['type' => 'github', 'uri' => 'a/b']]);
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

    private static function id(): RepositoryId
    {
        return new RepositoryId('github', 'owner/repo');
    }

    /**
     * @return list<non-empty-string>
     */
    private static function tags(RepositoryRecord $record): array
    {
        return \array_map(static fn(ReleaseRecord $release): string => $release->tag, $record->releases());
    }
}
