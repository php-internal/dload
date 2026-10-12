<?php

declare(strict_types=1);

namespace Internal\DLoad\Tests\Unit\Module\Registry;

use Internal\DLoad\Module\Registry\Internal\StoredVersionRegistry;
use Internal\DLoad\Module\Registry\Record\ReleaseRecord;
use Internal\DLoad\Module\Registry\RepositoryId;
use Internal\DLoad\Module\Repository\Exception\ApiException;
use Internal\DLoad\Module\Repository\Internal\GitHub\Exception\GitHubRateLimitException;
use Internal\DLoad\Service\Logger;
use Internal\DLoad\Tests\Unit\Module\Registry\Stub\ArrayReleaseSource;
use Internal\DLoad\Tests\Unit\Module\Registry\Stub\InMemoryRegistryStorage;
use Symfony\Component\Console\Output\BufferedOutput;
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
    public function hiddenReleasesAreStoredButNeverServed(): void
    {
        $source = new ArrayReleaseSource([
            new ReleaseRecord('draft', 'draft', hidden: true),
            new ReleaseRecord('v2', 'v2'),
            new ReleaseRecord('v1', 'v1'),
        ]);

        Assert::same(self::flatten($this->registry()->releases($this->id, $source)), ['v2', 'v1']);
        Assert::same($this->storage->load($this->id)?->count(), 3);

        // The stored list is served without a request and still leaves the hidden one out
        Assert::same(self::flatten($this->registry()->releases($this->id, $source)), ['v2', 'v1']);
        Assert::same($source->served, [0, 2]);
    }

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
    public function releaseDeletedUpstreamDoesNotShiftTheTail(): void
    {
        $source = ArrayReleaseSource::ofTags(['v6', 'v5', 'v4', 'v3', 'v2', 'v1']);

        // The first run stores the first page only
        $this->registry()->releases($this->id, $source)->current();
        $source->served = [];

        // `v5` is deleted upstream, so every older release moves up one position
        $source->delete('v5');
        $this->now += 601;
        $again = self::flatten($this->registry()->releases($this->id, $source));

        // The check drops `v5`; the tail continues from the true offset and `v3` is not skipped
        Assert::same($again, ['v6', 'v4', 'v3', 'v2', 'v1']);
        Assert::same($source->served, [0, 2, 4]);
    }

    #[Test]
    public function tailEndingInsideAPageCostsNoExtraRequest(): void
    {
        $source = ArrayReleaseSource::ofTags(['v6', 'v5', 'v4', 'v3', 'v2', 'v1']);
        $this->registry()->releases($this->id, $source)->current();
        $source->served = [];

        // The check leaves three stored, the last one inside page 2
        $source->publish('v7');
        $this->now += 601;
        $again = self::flatten($this->registry()->releases($this->id, $source));

        Assert::same($again, ['v7', 'v6', 'v5', 'v4', 'v3', 'v2', 'v1']);
        Assert::count($source->served, 4);
    }

    #[Test]
    public function tailEndingOnAPageBoundaryCostsOneRequestMore(): void
    {
        $source = ArrayReleaseSource::ofTags(['v6', 'v5', 'v4', 'v3', 'v2', 'v1']);
        self::take($this->registry()->releases($this->id, $source), 4);
        $source->served = [];

        $this->now += 601;
        $again = self::flatten($this->registry()->releases($this->id, $source));

        // The check, the page ending with the last stored release, and the page the tail needs
        Assert::same($again, ['v6', 'v5', 'v4', 'v3', 'v2', 'v1']);
        Assert::count($source->served, 3);
    }

    #[Test]
    public function tailSeenWholeByTheCheckGoesOnWithoutExtraRequest(): void
    {
        $source = ArrayReleaseSource::ofTags(['v6', 'v5', 'v4', 'v3', 'v2', 'v1']);
        $this->registry()->releases($this->id, $source)->current();
        $source->served = [];

        $this->now += 601;
        $again = self::flatten($this->registry()->releases($this->id, $source));

        Assert::same($again, ['v6', 'v5', 'v4', 'v3', 'v2', 'v1']);
        Assert::count($source->served, 3);
    }

    #[Test]
    public function releaseDeletedBelowTheCheckedSpanDoesNotShiftTheTail(): void
    {
        $source = ArrayReleaseSource::ofTags(['v8', 'v7', 'v6', 'v5', 'v4', 'v3', 'v2', 'v1']);
        self::take($this->registry()->releases($this->id, $source), 6);
        $source->served = [];

        // `v5` is deleted upstream, outside the page the check fetches
        $source->delete('v5');
        $this->now += 601;
        $again = self::flatten($this->registry()->releases($this->id, $source));

        // `v2` moved up into the stored part of the listing and is still loaded; the stored
        // `v5` stays until its download fails
        Assert::same($again, ['v8', 'v7', 'v6', 'v5', 'v4', 'v3', 'v2', 'v1']);
        Assert::same($this->stored(), ['v8', 'v7', 'v6', 'v5', 'v4', 'v3', 'v2', 'v1']);
        Assert::true($this->storage->load($this->id)->complete);
        Assert::count($source->served, 3);
    }

    #[Test]
    public function deletedLastStoredReleaseDoesNotShiftTheTail(): void
    {
        $source = ArrayReleaseSource::ofTags(['v8', 'v7', 'v6', 'v5', 'v4', 'v3', 'v2', 'v1']);
        self::take($this->registry()->releases($this->id, $source), 6);

        $source->delete('v3');
        $this->now += 601;
        $again = self::flatten($this->registry()->releases($this->id, $source));

        Assert::same($again, ['v8', 'v7', 'v6', 'v5', 'v4', 'v3', 'v2', 'v1']);
        Assert::same($this->stored(), $again);
    }

    #[Test]
    public function deletionsMovingTheTailOffItsPageAreFollowedUp(): void
    {
        $source = ArrayReleaseSource::ofTags(['v12', 'v11', 'v10', 'v9', 'v8', 'v7', 'v6', 'v5', 'v4', 'v3', 'v2', 'v1']);
        self::take($this->registry()->releases($this->id, $source), 8);

        // The last stored `v5` moves up three positions, off the page its stored count points at
        $source->delete('v8');
        $source->delete('v7');
        $source->delete('v6');
        $this->now += 601;
        $again = self::flatten($this->registry()->releases($this->id, $source));

        Assert::same($again, ['v12', 'v11', 'v10', 'v9', 'v8', 'v7', 'v6', 'v5', 'v4', 'v3', 'v2', 'v1']);
        Assert::same($this->stored(), $again);
    }

    #[Test]
    public function deepDeletionInALongTailCostsNoReload(): void
    {
        $tags = \array_map(static fn(int $i): string => "v$i", \range(2500, 1));
        $source = ArrayReleaseSource::ofTags($tags, perPage: 100);
        self::take($this->registry()->releases($this->id, $source), 2000);
        $source->served = [];

        $source->delete('v1000');
        $this->now += 601;
        self::take($this->registry()->releases($this->id, $source), 2050);

        // The check, the page holding the last stored release and the next one
        Assert::count($source->served, 3);
        Assert::same($this->stored(), \array_slice($tags, 0, 2101));
    }

    #[Test]
    public function releasesPublishedWithinTheTtlAreNeitherRepeatedNorAppended(): void
    {
        $source = ArrayReleaseSource::ofTags(['v6', 'v5', 'v4', 'v3', 'v2', 'v1']);
        $this->registry()->releases($this->id, $source)->current();

        // More releases than a page holds: the page at the stored count holds no stored one
        $source->publish('v9', 'v8', 'v7');
        $this->now += 100;
        $again = self::flatten($this->registry()->releases($this->id, $source));

        Assert::same($again, ['v6', 'v5', 'v4', 'v3', 'v2', 'v1']);
        Assert::same($this->stored(), $again);

        // The next check puts them on top
        $this->now += 601;
        Assert::same(self::flatten($this->registry()->releases($this->id, $source)), ['v9', 'v8', 'v7', 'v6', 'v5', 'v4', 'v3', 'v2', 'v1']);
    }

    #[Test]
    public function releaseDeletedWithinTheTtlDoesNotShiftTheTail(): void
    {
        $source = ArrayReleaseSource::ofTags(['v6', 'v5', 'v4', 'v3', 'v2', 'v1']);
        self::take($this->registry()->releases($this->id, $source), 4);

        $source->delete('v4');
        $this->now += 100;
        $again = self::flatten($this->registry()->releases($this->id, $source));

        Assert::same($again, ['v6', 'v5', 'v4', 'v3', 'v2', 'v1']);
        Assert::same($this->stored(), $again);
    }

    #[Test]
    public function failedCheckLeavesReleasesPublishedAboveTheTailToTheNextOne(): void
    {
        $source = ArrayReleaseSource::ofTags(['v8', 'v7', 'v6', 'v5', 'v4', 'v3', 'v2', 'v1']);
        $this->registry()->releases($this->id, $source)->current();

        $source->publish('n3', 'n2', 'n1');
        $source->fail();
        $this->now += 601;
        $pages = $this->registry()->releases($this->id, $source);
        Assert::same(self::tagsOf($pages->current()), ['v8', 'v7']);

        // The source is back for the tail
        $source->failure = null;
        Assert::same(self::flatten($pages), ['v8', 'v7', 'v6', 'v5', 'v4', 'v3', 'v2', 'v1']);
        Assert::same($this->stored(), ['v8', 'v7', 'v6', 'v5', 'v4', 'v3', 'v2', 'v1']);

        $this->now += 601;
        self::flatten($this->registry()->releases($this->id, $source));
        Assert::same($this->stored(), ['n3', 'n2', 'n1', 'v8', 'v7', 'v6', 'v5', 'v4', 'v3', 'v2', 'v1']);
    }

    #[Test]
    public function unreadableReleaseAboveTheTailDoesNotShiftIt(): void
    {
        $source = ArrayReleaseSource::ofTags(['v10', 'v9', 'v8', 'v7', 'v6', 'v5', 'v4', 'v3', 'v2', 'v1']);
        $source->unreadable = ['v9'];
        self::take($this->registry()->releases($this->id, $source), 5);
        Assert::same($this->stored(), ['v10', 'v8', 'v7', 'v6', 'v5']);

        // The check bails out on `v9`; the stored `v5` lies one position further than its count
        $this->now += 601;
        $again = self::flatten($this->registry()->releases($this->id, $source));

        Assert::same($again, ['v10', 'v8', 'v7', 'v6', 'v5', 'v4', 'v3', 'v2', 'v1']);
        Assert::same($this->stored(), $again);
    }

    #[Test]
    public function unreadableReleaseInTheStoredTailDoesNotShiftIt(): void
    {
        $source = ArrayReleaseSource::ofTags(['v10', 'v9', 'v8', 'v7', 'v6', 'v5', 'v4', 'v3', 'v2', 'v1']);
        $source->unreadable = ['v6'];
        self::take($this->registry()->releases($this->id, $source), 7);
        $source->served = [];

        $this->now += 601;
        $again = self::flatten($this->registry()->releases($this->id, $source));

        Assert::same($again, ['v10', 'v9', 'v8', 'v7', 'v5', 'v4', 'v3', 'v2', 'v1']);
        Assert::count($source->served, 3);

        // Every later check costs the first page only
        $source->served = [];
        $this->now += 601;
        self::flatten($this->registry()->releases($this->id, $source));
        Assert::same($source->served, [0]);
    }

    #[Test]
    public function releaseTurnedReadableAboveTheTailIsNotLoaded(): void
    {
        $source = ArrayReleaseSource::ofTags(['v10', 'v9', 'v8', 'v7', 'v6', 'v5', 'v4', 'v3', 'v2', 'v1']);
        $source->unreadable = ['v6'];
        self::take($this->registry()->releases($this->id, $source), 7);

        // `v6` lies above the last stored release, where only a check could see it
        $source->unreadable = [];
        $this->now += 601;
        $again = self::flatten($this->registry()->releases($this->id, $source));

        Assert::same($again, ['v10', 'v9', 'v8', 'v7', 'v5', 'v4', 'v3', 'v2', 'v1']);
    }

    #[Test]
    public function listingShorterThanTheStoredCountIsContinuedFromItsLastPage(): void
    {
        $source = ArrayReleaseSource::ofTags(['v8', 'v7', 'v6', 'v5', 'v4', 'v3', 'v2', 'v1']);
        self::take($this->registry()->releases($this->id, $source), 6);

        $source->delete('v5');
        $source->delete('v4');
        $source->delete('v3');
        $source->delete('v2');
        $this->now += 601;
        $again = self::flatten($this->registry()->releases($this->id, $source));

        Assert::same($again, ['v8', 'v7', 'v6', 'v5', 'v4', 'v3', 'v1']);
        Assert::same($this->stored(), $again);
        Assert::true($this->storage->load($this->id)->complete);
    }

    #[Test]
    public function listingEmptiedUpstreamLeavesNothingStored(): void
    {
        $source = ArrayReleaseSource::ofTags(['v4', 'v3', 'v2', 'v1']);
        $this->registry()->releases($this->id, $source)->current();

        foreach (['v4', 'v3', 'v2', 'v1'] as $tag) {
            $source->delete($tag);
        }
        $this->now += 601;

        Assert::same(self::flatten($this->registry()->releases($this->id, $source)), []);
        Assert::same($this->stored(), []);
        Assert::true($this->storage->load($this->id)->complete);
    }

    #[Test]
    public function tailIsLoadedOnlyAsFarAsTheConsumerGoes(): void
    {
        $source = ArrayReleaseSource::ofTags(['v8', 'v7', 'v6', 'v5', 'v4', 'v3', 'v2', 'v1']);
        self::take($this->registry()->releases($this->id, $source), 4);
        $source->served = [];

        $this->now += 601;
        self::take($this->registry()->releases($this->id, $source), 5);

        Assert::count($source->served, 3);
        Assert::same($this->stored(), ['v8', 'v7', 'v6', 'v5', 'v4', 'v3']);
        Assert::false($this->storage->load($this->id)->complete);
    }

    #[Test]
    public function failureWhileLoadingTheTailKeepsWhatWasLoaded(): void
    {
        $source = ArrayReleaseSource::ofTags(['v8', 'v7', 'v6', 'v5', 'v4', 'v3', 'v2', 'v1']);
        self::take($this->registry()->releases($this->id, $source), 4);
        $source->served = [];

        // The check and two pages of the tail succeed, the next one fails
        $source->failAfter = 3;
        $this->now += 601;
        try {
            self::flatten($this->registry()->releases($this->id, $source));
            Assert::fail('The failure of the tail request must reach the caller.');
        } catch (ApiException) {
        }

        Assert::same($this->stored(), ['v8', 'v7', 'v6', 'v5', 'v4', 'v3']);
        Assert::false($this->storage->load($this->id)->complete);

        $source->failAfter = \PHP_INT_MAX;
        $this->now += 601;
        Assert::same(self::flatten($this->registry()->releases($this->id, $source)), ['v8', 'v7', 'v6', 'v5', 'v4', 'v3', 'v2', 'v1']);
    }

    #[Test]
    public function unreadableReleaseIsNotTakenForADeletedOne(): void
    {
        $source = ArrayReleaseSource::ofTags(['v3', 'v2', 'v1'], perPage: 3);
        self::flatten($this->registry()->releases($this->id, $source));

        // The source fails to read `v2` this time; the stored list must keep it
        $source->unreadable = ['v2'];
        $source->publish('v4');
        $this->now += 601;
        $again = self::flatten($this->registry()->releases($this->id, $source));

        Assert::same($again, ['v3', 'v2', 'v1']);

        // Nothing was stored, so the next run checks again instead of waiting for the TTL
        $source->unreadable = [];
        $source->served = [];
        $fixed = self::flatten($this->registry()->releases($this->id, $source));

        Assert::same($fixed, ['v4', 'v3', 'v2', 'v1']);
        Assert::same($source->served, [0]);
    }

    #[Test]
    public function unreadableReleaseIsSkippedWhenNothingIsStored(): void
    {
        $source = ArrayReleaseSource::ofTags(['v3', 'v2', 'v1'], perPage: 3);
        $source->unreadable = ['v2'];

        Assert::same(self::flatten($this->registry()->releases($this->id, $source)), ['v3', 'v1']);
    }

    #[Test]
    public function failedTailLoadingStillServesTheStoredReleases(): void
    {
        $source = ArrayReleaseSource::ofTags(['v4', 'v3', 'v2', 'v1']);
        $this->registry()->releases($this->id, $source)->current();

        $source->fail();
        $pages = $this->registry()->releases($this->id, $source);

        // The stored page comes first; only the request for the tail fails
        Assert::same(self::tagsOf($pages->current()), ['v4', 'v3']);
        try {
            $pages->next();
            Assert::fail('The failure of the tail request must reach the caller.');
        } catch (ApiException) {
            Assert::false($this->storage->load($this->id)->complete);
        }
    }

    #[Test]
    public function rateLimitOnCheckFallsBackToStoredReleasesAndIsReportedOnce(): void
    {
        $other = new RepositoryId('github', 'other/repo');
        $source = ArrayReleaseSource::ofTags(['v2', 'v1']);
        self::flatten($this->registry()->releases($this->id, $source));
        self::flatten($this->registry()->releases($other, $source));

        $source->fail(new GitHubRateLimitException('Rate limit exceeded.', 'owner/repo', null));
        $this->now += 601;
        $output = new BufferedOutput();
        $registry = $this->registry(logger: new Logger($output));

        Assert::same(self::flatten($registry->releases($this->id, $source)), ['v2', 'v1']);
        Assert::same(self::flatten($registry->releases($other, $source)), ['v2', 'v1']);

        // Shown without any verbosity flag, but not for every repository of the run
        Assert::same(\substr_count($output->fetch(), 'rate limit prevents checking'), 1);
    }

    #[Test]
    public function ordinaryCheckFailureStaysOutOfTheDefaultOutput(): void
    {
        $source = ArrayReleaseSource::ofTags(['v1']);
        self::flatten($this->registry()->releases($this->id, $source));

        $source->fail();
        $this->now += 601;
        $output = new BufferedOutput();
        self::flatten($this->registry(logger: new Logger($output))->releases($this->id, $source));

        Assert::same($output->fetch(), '');
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

        $registry->attach($this->id, 'rr');
        $registry->attach($this->id, 'rr');
        $registry->attach($this->id, 'roadrunner');

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
     * Consumes pages until the given number of releases has been served.
     *
     * @param \Generator<int, list<ReleaseRecord>, mixed, void> $pages
     */
    private static function take(\Generator $pages, int $count): void
    {
        $seen = 0;
        foreach ($pages as $page) {
            $seen += \count($page);
            if ($seen >= $count) {
                break;
            }
        }
    }

    /**
     * @param list<ReleaseRecord> $releases
     * @return list<non-empty-string>
     */
    private static function tagsOf(array $releases): array
    {
        return \array_map(static fn(ReleaseRecord $release): string => $release->tag, $releases);
    }

    /**
     * @return list<non-empty-string>
     */
    private function stored(): array
    {
        return self::tagsOf($this->storage->load($this->id)?->releases() ?? []);
    }

    private function registry(bool $refresh = false, ?Logger $logger = null): StoredVersionRegistry
    {
        return new StoredVersionRegistry($this->storage, 600, $logger ?? new Logger(), $refresh, fn(): int => $this->now);
    }
}
