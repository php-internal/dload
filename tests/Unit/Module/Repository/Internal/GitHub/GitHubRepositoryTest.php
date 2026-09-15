<?php

declare(strict_types=1);

namespace Internal\DLoad\Tests\Unit\Module\Repository\Internal\GitHub;

use Internal\DLoad\Module\Config\Schema\GitHub as GitHubConfig;
use Internal\DLoad\Module\HttpClient\Internal\NyholmFactoryImpl;
use Internal\DLoad\Module\Registry\Internal\PassThroughRegistry;
use Internal\DLoad\Module\Registry\Internal\StoredVersionRegistry;
use Internal\DLoad\Module\Registry\Record\ReleaseRecord;
use Internal\DLoad\Module\Registry\Record\RepositoryRecord;
use Internal\DLoad\Module\Registry\RepositoryId;
use Internal\DLoad\Module\Registry\VersionRegistry;
use Internal\DLoad\Module\Repository\Exception\RateLimitException;
use Internal\DLoad\Module\Repository\Internal\GitHub\Api\Client;
use Internal\DLoad\Module\Repository\Internal\GitHub\Api\RepositoryApi;
use Internal\DLoad\Module\Repository\Internal\GitHub\GitHubReleaseSource;
use Internal\DLoad\Module\Repository\Internal\GitHub\GitHubRepository;
use Internal\DLoad\Module\Repository\ReleaseInterface;
use Internal\DLoad\Service\Logger;
use Internal\DLoad\Tests\Unit\Module\Registry\Stub\InMemoryRegistryStorage;
use Internal\DLoad\Tests\Unit\Module\Repository\Internal\GitHub\Stub\PagedClientStub;
use Internal\DLoad\Tests\Unit\Module\Repository\Internal\GitHub\Stub\ScriptedRegistryStub;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Expect;
use Testo\Test;

#[Covers(GitHubRepository::class)]
#[Covers(GitHubReleaseSource::class)]
final class GitHubRepositoryTest
{
    #[Test]
    public function everyReleasesPageIsRequestedOnce(): void
    {
        $client = new PagedClientStub(pages: 3);
        $repository = self::createRepository($client);

        $releases = \iterator_to_array($repository->getReleases(), false);

        Assert::same(\count($releases), 300);
        Assert::same($client->requestedPages(), [1, 2, 3]);
    }

    #[Test]
    public function pagesAreLoadedOnlyWhenNeeded(): void
    {
        $client = new PagedClientStub(pages: 3);
        $repository = self::createRepository($client);

        // Consume the whole first page, but nothing beyond it
        $seen = 0;
        foreach ($repository->getReleases() as $release) {
            unset($release);
            if (++$seen === 100) {
                break;
            }
        }

        Assert::same($client->requestedPages(), [1]);
    }

    #[Test]
    public function destroyDoesNotLoadTheRemainingPages(): void
    {
        $client = new PagedClientStub(pages: 3);
        $repository = self::createRepository($client);
        $repository->getReleases()->first();

        $repository->destroy();

        Assert::same($client->requestedPages(), [1]);
    }

    #[Test]
    public function releasesAreRequestedAHundredPerPage(): void
    {
        $client = new PagedClientStub(pages: 1);
        $repository = self::createRepository($client);

        \iterator_to_array($repository->getReleases(), false);

        Assert::same($client->requests, ['page=1&per_page=100']);
    }

    #[Test]
    public function secondRunIsServedFromTheRegistryWithoutRequests(): void
    {
        $storage = new InMemoryRegistryStorage();

        $firstClient = new PagedClientStub(pages: 2);
        $firstRun = self::names(self::createRepository($firstClient, self::registry($storage)));

        // A second run in a fresh process with the registry carried over
        $secondClient = new PagedClientStub(pages: 2);
        $secondRun = self::names(self::createRepository($secondClient, self::registry($storage)));

        Assert::same($firstClient->requestedPages(), [1, 2]);
        Assert::same($secondClient->requestedPages(), []);
        Assert::same($secondRun, $firstRun);
        Assert::same(\count($secondRun), 200);
    }

    #[Test]
    public function olderReleasesAreLoadedFromTheApiWhenTheRegistryRunsOut(): void
    {
        $storage = new InMemoryRegistryStorage();

        // The first run needs the first page only
        $firstClient = new PagedClientStub(pages: 3);
        foreach (self::createRepository($firstClient, self::registry($storage))->getReleases() as $release) {
            unset($release);
            break;
        }

        // The second run needs everything: the stored page costs nothing, the rest is fetched
        $secondClient = new PagedClientStub(pages: 3);
        $all = self::names(self::createRepository($secondClient, self::registry($storage)));

        Assert::same($firstClient->requestedPages(), [1]);
        Assert::same($secondClient->requestedPages(), [2, 3]);
        Assert::same(\count($all), 300);
    }

    #[Test]
    public function draftReleasesAreNeitherServedNorStored(): void
    {
        $storage = new InMemoryRegistryStorage();

        $client = new PagedClientStub(pages: 1, drafts: 2);
        $names = self::names(self::createRepository($client, self::registry($storage)));

        Assert::same(\count($names), 100);
        Assert::false(\in_array('draft-1', $names, true));

        // The draft keeps its listing position as a hidden placeholder, so the stored count still
        // maps onto the API paging and the second page is the next request, not the first one again
        Assert::same($client->requestedPages(), [1, 2]);
        $stored = $storage->load(new RepositoryId(GitHubRepository::TYPE, 'owner/repo'));
        Assert::same($stored?->count(), 102);
        Assert::true($stored?->releases()[0]->hidden);
        Assert::same($stored?->releases()[0]->assets, []);
    }

    #[Test]
    public function unreadableReleasesAreCountedOnThePage(): void
    {
        $page = (new GitHubReleaseSource(self::api(new PagedClientStub(pages: 1, broken: 2))))->pages()->current();

        Assert::same(\count($page->releases), 98);
        Assert::same($page->skipped, 2);
    }

    #[Test]
    public function assetDigestReportedByTheApiIsStored(): void
    {
        $storage = new InMemoryRegistryStorage();

        self::createRepository(new PagedClientStub(pages: 1), self::registry($storage))->getReleases()->first();

        $asset = $storage->load(new RepositoryId(GitHubRepository::TYPE, 'owner/repo'))?->releases()[0]->assets[0];
        Assert::same($asset?->digest, 'sha256:' . \hash('sha256', 'v1.0.1'));
    }

    #[Test]
    public function tailIsLoadedFromInsideAPageWhenTheStoredCountIsNotPageAligned(): void
    {
        // A fresh record holds the first 50 releases: the tail starts in the middle of API page 1
        $storage = new InMemoryRegistryStorage();
        $storage->save((new RepositoryRecord(new RepositoryId(GitHubRepository::TYPE, 'owner/repo'), checkedAt: \time()))->withHead(\array_map(
            static fn(int $i): ReleaseRecord => new ReleaseRecord(\sprintf('v1.0.%d', $i), \sprintf('v1.0.%d', $i)),
            \range(1, 50),
        )));

        $client = new PagedClientStub(pages: 2);
        $all = self::names(self::createRepository($client, self::registry($storage)));

        Assert::same($client->requestedPages(), [1, 2]);
        Assert::same(\count($all), 200);
        Assert::same(\count(\array_unique($all)), 200);
        Assert::same($all[50], 'v1.0.51');
    }

    #[Test]
    public function theSameCollectionIsReturnedOnEveryCall(): void
    {
        $repository = self::createRepository(new PagedClientStub(pages: 1));

        Assert::same($repository->getReleases(), $repository->getReleases());
    }

    #[Test]
    public function nameIsTheOwnerAndRepository(): void
    {
        Assert::same(self::createRepository(new PagedClientStub(pages: 1))->getName(), 'owner/repo');
    }

    #[Test]
    public function aReleaseWithAnUnparsableTagIsSkipped(): void
    {
        $registry = new ScriptedRegistryStub([[self::record('not-a-version'), self::record('v1.0.1')]]);
        $repository = self::createRepository(new PagedClientStub(pages: 1), $registry);

        $names = self::names($repository);

        Assert::same($names, ['v1.0.1']);
    }

    #[Test]
    public function aFailureOfTheFirstPageReachesTheCaller(): void
    {
        $registry = new ScriptedRegistryStub([new \RuntimeException('missing repository')]);
        $repository = self::createRepository(new PagedClientStub(pages: 1), $registry);

        Expect::exception(\RuntimeException::class)->withMessage('missing repository');

        self::names($repository);
    }

    #[Test]
    public function aRateLimitOnALaterPageReachesTheCaller(): void
    {
        $registry = new ScriptedRegistryStub([
            [self::record('v1.0.2')],
            new RateLimitException('API rate limit exceeded'),
        ]);
        $repository = self::createRepository(new PagedClientStub(pages: 1), $registry);

        Expect::exception(RateLimitException::class)->withMessage('API rate limit exceeded');

        self::names($repository);
    }

    #[Test]
    public function anOrdinaryFailureOfALaterPageStopsPaginationAndKeepsWhatLoaded(): void
    {
        $registry = new ScriptedRegistryStub([
            [self::record('v1.0.2')],
            new \RuntimeException('transient network error'),
        ]);
        $repository = self::createRepository(new PagedClientStub(pages: 1), $registry);

        // The first page is enough to keep going, so the later failure only ends the listing
        Assert::same(self::names($repository), ['v1.0.2']);
    }

    /**
     * @param non-empty-string $tag
     */
    private static function record(string $tag): ReleaseRecord
    {
        return new ReleaseRecord($tag, $tag);
    }

    private static function createRepository(
        PagedClientStub $client,
        VersionRegistry $registry = new PassThroughRegistry(),
    ): GitHubRepository {
        return new GitHubRepository(self::api($client), 'owner', 'repo', new Logger(), $registry);
    }

    private static function api(PagedClientStub $client): RepositoryApi
    {
        $logger = new Logger();
        $httpFactory = new NyholmFactoryImpl($logger);

        return new RepositoryApi(
            new Client($httpFactory, $client, new GitHubConfig()),
            $httpFactory,
            'owner',
            'repo',
            $logger,
        );
    }

    private static function registry(InMemoryRegistryStorage $storage): StoredVersionRegistry
    {
        return new StoredVersionRegistry($storage, 600, new Logger());
    }

    /**
     * @return list<non-empty-string>
     */
    private static function names(GitHubRepository $repository): array
    {
        return \array_map(
            static fn(ReleaseInterface $release): string => $release->getName(),
            \iterator_to_array($repository->getReleases(), false),
        );
    }
}
