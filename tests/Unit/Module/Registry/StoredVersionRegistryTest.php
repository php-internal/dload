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
    public function releaseDeletedBelowTheCheckedSpanReloadsTheTail(): void
    {
        $source = ArrayReleaseSource::ofTags(['v8', 'v7', 'v6', 'v5', 'v4', 'v3', 'v2', 'v1']);

        // The first run stores three pages and stops
        $pages = $this->registry()->releases($this->id, $source);
        self::take($pages, 6);
        $source->served = [];

        // `v3` is deleted upstream, outside the page the check will fetch
        $source->delete('v3');
        $this->now += 601;
        $again = self::flatten($this->registry()->releases($this->id, $source));

        // The stored `v3` is served as it was, but `v2` is not skipped: the check confirms
        // page 1 only, `v3` is on neither the page at offset 5 nor the one after it, and the
        // tail is loaded again from offset 2
        Assert::same($again, ['v8', 'v7', 'v6', 'v5', 'v4', 'v3', 'v2', 'v1']);
        Assert::same($source->served, [0, 4, 6, 2, 4, 6]);

        $record = $this->storage->load($this->id);
        Assert::same(self::tagsOf($record->releases()), ['v8', 'v7', 'v6', 'v5', 'v4', 'v2', 'v1']);
        Assert::true($record->complete);
    }

    #[Test]
    public function vanishedDraftPlaceholderReloadsTheTail(): void
    {
        $source = new ArrayReleaseSource([
            new ReleaseRecord('v6', 'v6'),
            new ReleaseRecord('v5', 'v5'),
            new ReleaseRecord('v4', 'v4'),
            new ReleaseRecord('draft', 'draft', hidden: true),
            new ReleaseRecord('v2', 'v2'),
            new ReleaseRecord('v1', 'v1'),
        ]);
        self::take($this->registry()->releases($this->id, $source), 3);
        Assert::same($this->storage->load($this->id)?->count(), 4);
        $source->served = [];

        // The draft is gone from the listing, as after its deletion or with a token that cannot see it
        $source->delete('draft');
        $this->now += 601;
        $again = self::flatten($this->registry()->releases($this->id, $source));

        Assert::same($again, ['v6', 'v5', 'v4', 'v2', 'v1']);
        Assert::same($source->served, [0, 2, 4, 2, 4]);
        Assert::false($this->storage->load($this->id)->has('draft'));
    }

    #[Test]
    public function tailBelowTheCheckedSpanIsConfirmedOnThePageItContinuesFrom(): void
    {
        $source = ArrayReleaseSource::ofTags(['v6', 'v5', 'v4', 'v3', 'v2', 'v1']);
        $this->registry()->releases($this->id, $source)->current();
        $source->served = [];

        // The check confirms page 1 and leaves three stored, the last one in the middle of
        // page 2: it is confirmed on the page the tail continues from
        $source->publish('v7');
        $this->now += 601;
        $again = self::flatten($this->registry()->releases($this->id, $source));

        Assert::same($again, ['v7', 'v6', 'v5', 'v4', 'v3', 'v2', 'v1']);
        Assert::same($source->served, [0, 2, 4, 6]);
    }

    #[Test]
    public function tailEndingOnAPageBoundaryCostsOneRequestToConfirm(): void
    {
        $source = ArrayReleaseSource::ofTags(['v6', 'v5', 'v4', 'v3', 'v2', 'v1']);
        self::take($this->registry()->releases($this->id, $source), 4);
        $source->served = [];

        // Four are stored, the check confirms two: the page holding the fourth is fetched to
        // confirm it before the tail goes on
        $this->now += 601;
        $again = self::flatten($this->registry()->releases($this->id, $source));

        Assert::same($again, ['v6', 'v5', 'v4', 'v3', 'v2', 'v1']);
        Assert::same($source->served, [0, 2, 4]);
    }

    #[Test]
    public function releasesPublishedWithinTheTtlOverlapTheTailWithoutRepeating(): void
    {
        $source = ArrayReleaseSource::ofTags(['v6', 'v5', 'v4', 'v3', 'v2', 'v1']);
        $this->registry()->releases($this->id, $source)->current();
        $source->served = [];

        // The record is fresh, so nothing is confirmed; the listing shifted down by one
        $source->publish('v7');
        $this->now += 100;
        $again = self::flatten($this->registry()->releases($this->id, $source));

        Assert::same($again, ['v6', 'v5', 'v4', 'v3', 'v2', 'v1']);
        Assert::same($source->served, [2, 4, 6]);
    }

    #[Test]
    public function failedCheckConfirmsNothingAboutTheStoredTail(): void
    {
        $source = ArrayReleaseSource::ofTags(['v6', 'v5', 'v4', 'v3', 'v2', 'v1']);
        self::take($this->registry()->releases($this->id, $source), 4);
        $source->served = [];

        $source->fail();
        $this->now += 601;
        $pages = $this->registry()->releases($this->id, $source);
        Assert::same(self::tagsOf($pages->current()), ['v6', 'v5', 'v4', 'v3']);

        // The source is back: the check compared nothing, so the tail is confirmed from its
        // last stored release on, which lies on the page needed anyway
        $source->failure = null;
        Assert::same(self::flatten($pages), ['v6', 'v5', 'v4', 'v3', 'v2', 'v1']);
        Assert::same($source->served, [2, 4]);
    }

    #[Test]
    public function releaseDeletedBelowTheHeadIsNoticedAfterAFailedCheck(): void
    {
        $source = ArrayReleaseSource::ofTags(['v8', 'v7', 'v6', 'v5', 'v4', 'v3', 'v2', 'v1']);
        self::take($this->registry()->releases($this->id, $source), 6);
        $source->served = [];

        // `v3` is deleted upstream while the check cannot reach the source
        $source->delete('v3');
        $source->fail();
        $this->now += 601;
        $pages = $this->registry()->releases($this->id, $source);
        $pages->current();

        $source->failure = null;
        Assert::same(self::flatten($pages), ['v8', 'v7', 'v6', 'v5', 'v4', 'v3', 'v2', 'v1']);
        Assert::same($source->served, [4, 6, 0, 2, 4, 6]);

        // Neither a lost release nor a ghost is left behind
        Assert::same(self::tagsOf($this->storage->load($this->id)->releases()), ['v8', 'v7', 'v6', 'v5', 'v4', 'v2', 'v1']);
    }

    #[Test]
    public function unreadableReleaseOnTheFirstPageDoesNotHideADeletionInTheTail(): void
    {
        $source = ArrayReleaseSource::ofTags(['v10', 'v9', 'v8', 'v7', 'v6', 'v5', 'v4', 'v3', 'v2', 'v1']);
        $source->unreadable = ['v9'];
        self::take($this->registry()->releases($this->id, $source), 5);
        Assert::same(self::tagsOf($this->storage->load($this->id)->releases()), ['v10', 'v8', 'v7', 'v6', 'v5']);
        $source->served = [];

        // The check bails out on `v9` and leaves the record as it is; the stored `v5` is one
        // position further down the listing than the stored count says, and is found there
        $this->now += 601;
        $pages = $this->registry()->releases($this->id, $source);
        self::take($pages, 7);

        Assert::same($source->served, [0, 4, 6]);
        Assert::same(self::tagsOf($this->storage->load($this->id)->releases()), ['v10', 'v8', 'v7', 'v6', 'v5', 'v4', 'v3']);
        $source->served = [];

        // Now `v5` and `v4` are deleted upstream too: the stored `v3` moved up past the page
        // start, the last page has no further one to look at, and the tail is loaded again
        $source->delete('v5');
        $source->delete('v4');
        $this->now += 601;
        $again = self::flatten($this->registry()->releases($this->id, $source));

        Assert::same($again, ['v10', 'v8', 'v7', 'v6', 'v5', 'v4', 'v3', 'v2', 'v1']);
        Assert::same($source->served, [0, 6, 0, 2, 4, 6]);
        Assert::same(self::tagsOf($this->storage->load($this->id)->releases()), ['v10', 'v8', 'v7', 'v6', 'v3', 'v2', 'v1']);
    }

    #[Test]
    public function unreadableReleaseBelowTheCheckedPageDoesNotMakeEveryCheckReloadTheTail(): void
    {
        $source = ArrayReleaseSource::ofTags(['v10', 'v9', 'v8', 'v7', 'v6', 'v5', 'v4', 'v3', 'v2', 'v1']);
        $source->unreadable = ['v6'];
        self::take($this->registry()->releases($this->id, $source), 7);
        Assert::same(self::tagsOf($this->storage->load($this->id)->releases()), ['v10', 'v9', 'v8', 'v7', 'v5', 'v4', 'v3']);
        $source->served = [];

        // The check confirms page 1 and knows nothing of `v6`; the stored `v3` lies one position
        // further than the stored count says and is found on the page fetched for it
        $this->now += 601;
        $again = self::flatten($this->registry()->releases($this->id, $source));

        Assert::same($again, ['v10', 'v9', 'v8', 'v7', 'v5', 'v4', 'v3', 'v2', 'v1']);
        Assert::same($source->served, [0, 6, 8]);
        Assert::same(self::tagsOf($this->storage->load($this->id)->releases()), ['v10', 'v9', 'v8', 'v7', 'v5', 'v4', 'v3', 'v2', 'v1']);

        $source->served = [];
        $this->now += 601;
        self::flatten($this->registry()->releases($this->id, $source));
        Assert::same($source->served, [0]);
    }

    #[Test]
    public function lastStoredReleaseMovedDownToTheNextPageIsStillFound(): void
    {
        $source = ArrayReleaseSource::ofTags(['v10', 'v9', 'v8', 'v7', 'v6', 'v5', 'v4', 'v3', 'v2', 'v1']);
        $source->unreadable = ['v9', 'v5'];
        self::take($this->registry()->releases($this->id, $source), 4);
        Assert::same(self::tagsOf($this->storage->load($this->id)->releases()), ['v10', 'v8', 'v7', 'v6']);
        $source->served = [];

        // The stored `v6` opens page 3 while the stored count points at page 2: the next page
        // is looked at before the tail is given up
        $this->now += 601;
        $again = self::flatten($this->registry()->releases($this->id, $source));

        Assert::same($again, ['v10', 'v8', 'v7', 'v6', 'v4', 'v3', 'v2', 'v1']);
        Assert::same($source->served, [0, 2, 4, 6, 8]);
    }

    #[Test]
    public function anchorBeyondTheEndOfTheListingReloadsTheTail(): void
    {
        $source = ArrayReleaseSource::ofTags(['v8', 'v7', 'v6', 'v5', 'v4', 'v3', 'v2', 'v1']);
        self::take($this->registry()->releases($this->id, $source), 6);
        $source->served = [];

        // Three releases below page 1 are gone: the last stored one would sit past the end now,
        // and the source has no page to answer with
        $source->delete('v4');
        $source->delete('v3');
        $source->delete('v2');
        $this->now += 601;
        $again = self::flatten($this->registry()->releases($this->id, $source));

        Assert::same($again, ['v8', 'v7', 'v6', 'v5', 'v4', 'v3', 'v1']);
        Assert::same($source->served, [0, 4, 2, 4]);
        Assert::same(self::tagsOf($this->storage->load($this->id)->releases()), ['v8', 'v7', 'v6', 'v5', 'v1']);
    }

    #[Test]
    public function failureWhileReloadingTheTailKeepsTheStoredReleases(): void
    {
        $source = ArrayReleaseSource::ofTags(['v6', 'v5', 'v4', 'v3', 'v2', 'v1']);
        self::take($this->registry()->releases($this->id, $source), 4);
        $source->served = [];

        $source->delete('v3');
        $this->now += 601;
        $pages = $this->registry()->releases($this->id, $source);
        $pages->current();

        // The anchor misses, and the source fails on the very next page
        $source->served = [];
        $source->failAfter = 1;
        try {
            $pages->next();
            Assert::fail('The failure of the reload must reach the caller.');
        } catch (ApiException) {
        }

        // Nothing was dropped: the stored list serves the next run under the same failure
        Assert::same($source->served, [2]);
        Assert::same(self::tagsOf($this->storage->load($this->id)->releases()), ['v6', 'v5', 'v4', 'v3']);
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

    private function registry(bool $refresh = false, ?Logger $logger = null): StoredVersionRegistry
    {
        return new StoredVersionRegistry($this->storage, 600, $logger ?? new Logger(), $refresh, fn(): int => $this->now);
    }
}
