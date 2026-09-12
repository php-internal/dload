<?php

declare(strict_types=1);

namespace Internal\DLoad\Tests\Unit\Module\Registry;

use Internal\DLoad\Module\Registry\Internal\StoredVersionRegistry;
use Internal\DLoad\Module\Registry\Record\ReleaseRecord;
use Internal\DLoad\Module\Registry\RepositoryId;
use Internal\DLoad\Module\Repository\Exception\ApiException;
use Internal\DLoad\Service\Logger;
use Internal\DLoad\Tests\Unit\Module\Registry\Stub\ArrayReleaseSource;
use Internal\DLoad\Tests\Unit\Module\Registry\Stub\InMemoryRegistryStorage;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Lifecycle\BeforeTest;
use Testo\Test;

#[Covers(StoredVersionRegistry::class)]
final class StoredVersionRegistryTest
{
    private InMemoryRegistryStorage $storage;
    private RepositoryId $id;
    private int $now = 1_000_000;

    #[Test]
    public function firstRunLoadsOnlyThePagesTheConsumerNeeds(): void
    {
        $source = ArrayReleaseSource::ofTags(['v6', 'v5', 'v4', 'v3', 'v2', 'v1']);
        $registry = $this->registry();

        $pages = $registry->releases($this->id, $source);
        $first = self::tagsOf($pages->current());

        Assert::same($first, ['v6', 'v5']);
        Assert::same($source->served, [0]);

        // The record already holds what was fetched, marked as incomplete
        $record = $this->storage->load($this->id);
        Assert::same(self::tagsOf($record->releases()), ['v6', 'v5']);
        Assert::false($record->complete);
        Assert::same($record->checkedAt, $this->now);
    }

    #[Test]
    public function olderReleasesAreLoadedOnDemandAndPersisted(): void
    {
        $source = ArrayReleaseSource::ofTags(['v6', 'v5', 'v4', 'v3', 'v2', 'v1']);
        $registry = $this->registry();

        $all = self::flatten($registry->releases($this->id, $source));

        Assert::same($all, ['v6', 'v5', 'v4', 'v3', 'v2', 'v1']);
        Assert::same($source->served, [0, 2, 4]);

        $record = $this->storage->load($this->id);
        Assert::same(self::tagsOf($record->releases()), ['v6', 'v5', 'v4', 'v3', 'v2', 'v1']);
        Assert::true($record->complete);
    }

    #[Test]
    public function freshRecordIsServedWithoutAnyRequest(): void
    {
        $source = ArrayReleaseSource::ofTags(['v3', 'v2', 'v1']);
        self::flatten($this->registry()->releases($this->id, $source));
        $source->served = [];

        $this->now += 100;
        $again = self::flatten($this->registry()->releases($this->id, $source));

        Assert::same($again, ['v3', 'v2', 'v1']);
        Assert::same($source->served, []);
    }

    #[Test]
    public function staleRecordIsCheckedWithASinglePageWhenNothingIsNew(): void
    {
        $source = ArrayReleaseSource::ofTags(['v3', 'v2', 'v1']);
        self::flatten($this->registry()->releases($this->id, $source));
        $source->served = [];

        $this->now += 601;
        $again = self::flatten($this->registry()->releases($this->id, $source));

        Assert::same($again, ['v3', 'v2', 'v1']);
        Assert::same($source->served, [0]);
        Assert::same($this->storage->load($this->id)->checkedAt, $this->now);
    }

    #[Test]
    public function newReleasesAreFetchedUntilAKnownOneIsReached(): void
    {
        $source = ArrayReleaseSource::ofTags(['v3', 'v2', 'v1']);
        self::flatten($this->registry()->releases($this->id, $source));
        $source->served = [];

        // Three releases were published: they span two pages, the second one reaches `v3`
        $source->publish('v6', 'v5', 'v4');
        $this->now += 601;
        $again = self::flatten($this->registry()->releases($this->id, $source));

        Assert::same($again, ['v6', 'v5', 'v4', 'v3', 'v2', 'v1']);
        Assert::same($source->served, [0, 2]);
    }

    #[Test]
    public function firstPageOverwritesStoredReleasesOnCheck(): void
    {
        $source = new ArrayReleaseSource([new ReleaseRecord('v1', 'v1', assets: [])]);
        self::flatten($this->registry()->releases($this->id, $source));

        // Assets were attached after the release had been stored
        $updated = new ArrayReleaseSource([
            new ReleaseRecord('v1', 'v1', assets: [new \Internal\DLoad\Module\Registry\Record\AssetRecord('rr.zip', 'https://x/rr.zip')]),
        ]);
        $this->now += 601;
        self::flatten($this->registry()->releases($this->id, $updated));

        Assert::count($this->storage->load($this->id)->releases()[0]->assets, 1);
    }

    #[Test]
    public function refreshFlagIgnoresTheTtl(): void
    {
        $source = ArrayReleaseSource::ofTags(['v2', 'v1']);
        self::flatten($this->registry()->releases($this->id, $source));
        $source->served = [];

        $source->publish('v3');
        self::flatten($this->registry(refresh: true)->releases($this->id, $source));

        Assert::same($source->served, [0]);
        Assert::same(self::tagsOf($this->storage->load($this->id)->releases()), ['v3', 'v2', 'v1']);
    }

    #[Test]
    public function failedCheckFallsBackToStoredReleases(): void
    {
        $source = ArrayReleaseSource::ofTags(['v2', 'v1']);
        self::flatten($this->registry()->releases($this->id, $source));

        $source->fail();
        $this->now += 601;
        $again = self::flatten($this->registry()->releases($this->id, $source));

        Assert::same($again, ['v2', 'v1']);
    }

    #[Test]
    public function failedCheckWithoutStoredReleasesIsReported(): void
    {
        $source = ArrayReleaseSource::ofTags(['v1']);
        $source->fail();

        try {
            self::flatten($this->registry()->releases($this->id, $source));
            Assert::fail('The failure of the source must reach the caller when nothing is stored.');
        } catch (ApiException) {
            Assert::same($this->storage->records, []);
        }
    }

    #[Test]
    public function storageFailureDoesNotBreakTheListing(): void
    {
        $this->storage->failOnSave = true;
        $source = ArrayReleaseSource::ofTags(['v2', 'v1']);

        Assert::same(self::flatten($this->registry()->releases($this->id, $source)), ['v2', 'v1']);
    }

    #[Test]
    public function forgetDropsTheReleaseAndForcesTheNextCheck(): void
    {
        $source = ArrayReleaseSource::ofTags(['v3', 'v2', 'v1']);
        $registry = $this->registry();
        self::flatten($registry->releases($this->id, $source));
        $source->served = [];

        // `v3` was deleted upstream and its download failed
        $registry->forget($this->id, 'v3');

        $record = $this->storage->load($this->id);
        Assert::same(self::tagsOf($record->releases()), ['v2', 'v1']);
        Assert::null($record->checkedAt);

        // The record is fresh by time, yet the next listing asks the source again
        $again = self::flatten($this->registry()->releases($this->id, $source));
        Assert::same($source->served, [0]);
        Assert::same($again, ['v3', 'v2', 'v1']);
    }

    #[Test]
    public function forgetOfAnUnknownReleaseChangesNothing(): void
    {
        $source = ArrayReleaseSource::ofTags(['v1']);
        self::flatten($this->registry()->releases($this->id, $source));
        $saves = $this->storage->saves;

        $this->registry()->forget($this->id, 'v9');
        $this->registry()->forget(new RepositoryId('github', 'other/repo'), 'v1');

        Assert::same($this->storage->saves, $saves);
        Assert::same($this->storage->load($this->id)->checkedAt, $this->now);
    }

    #[Test]
    public function attachRecordsTheSoftwareOnce(): void
    {
        $registry = $this->registry();

        $registry->attach('rr', $this->id);
        $registry->attach('rr', $this->id);
        $registry->attach('roadrunner', $this->id);

        Assert::same($this->storage->load($this->id)->software, ['rr', 'roadrunner']);
        Assert::same($this->storage->saves, 2);
    }

    #[BeforeTest]
    protected function prepare(): void
    {
        $this->storage = new InMemoryRegistryStorage();
        $this->id = new RepositoryId('github', 'owner/repo');
        $this->now = 1_000_000;
    }

    /**
     * @param iterable<list<ReleaseRecord>> $pages
     * @return list<non-empty-string>
     */
    private static function flatten(iterable $pages): array
    {
        $tags = [];
        foreach ($pages as $page) {
            $tags = [...$tags, ...self::tagsOf($page)];
        }

        return $tags;
    }

    /**
     * @param list<ReleaseRecord> $releases
     * @return list<non-empty-string>
     */
    private static function tagsOf(array $releases): array
    {
        return \array_map(static fn(ReleaseRecord $release): string => $release->tag, $releases);
    }

    private function registry(bool $refresh = false): StoredVersionRegistry
    {
        return new StoredVersionRegistry($this->storage, 600, new Logger(), $refresh, fn(): int => $this->now);
    }
}
