<?php

declare(strict_types=1);

namespace Internal\DLoad\Tests\Unit\Module\Repository\Internal\GitLab;

use Internal\DLoad\Module\Config\Schema\GitLab as GitLabConfig;
use Internal\DLoad\Module\HttpClient\Internal\NyholmFactoryImpl;
use Internal\DLoad\Module\Registry\Internal\PassThroughRegistry;
use Internal\DLoad\Module\Registry\Internal\StoredVersionRegistry;
use Internal\DLoad\Module\Registry\Record\ReleaseRecord;
use Internal\DLoad\Module\Registry\Record\RepositoryRecord;
use Internal\DLoad\Module\Registry\RepositoryId;
use Internal\DLoad\Module\Registry\VersionRegistry;
use Internal\DLoad\Module\Repository\Exception\RateLimitException;
use Internal\DLoad\Module\Repository\Internal\GitLab\Api\Client;
use Internal\DLoad\Module\Repository\Internal\GitLab\Api\RepositoryApi;
use Internal\DLoad\Module\Repository\Internal\GitLab\GitLabReleaseSource;
use Internal\DLoad\Module\Repository\Internal\GitLab\GitLabRepository;
use Internal\DLoad\Module\Repository\ReleaseInterface;
use Internal\DLoad\Service\Logger;
use Internal\DLoad\Tests\Unit\Module\Registry\Stub\InMemoryRegistryStorage;
use Internal\DLoad\Tests\Unit\Module\Repository\Internal\GitLab\Stub\PagedClientStub;
use Internal\DLoad\Tests\Unit\Module\Repository\Internal\GitLab\Stub\ScriptedRegistryStub;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Expect;
use Testo\Test;

#[Covers(GitLabRepository::class)]
#[Covers(GitLabReleaseSource::class)]
final class GitLabRepositoryTest
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
        \iterator_to_array(self::createRepository($firstClient, self::registry($storage))->getReleases(), false);

        $secondClient = new PagedClientStub(pages: 2);
        $secondRun = \iterator_to_array(self::createRepository($secondClient, self::registry($storage))->getReleases(), false);

        Assert::same($firstClient->requestedPages(), [1, 2]);
        Assert::same($secondClient->requestedPages(), []);
        Assert::same(\count($secondRun), 200);
    }

    #[Test]
    public function olderReleasesAreLoadedFromTheApiWhenTheRegistryRunsOut(): void
    {
        $storage = new InMemoryRegistryStorage();

        $firstClient = new PagedClientStub(pages: 3);
        foreach (self::createRepository($firstClient, self::registry($storage))->getReleases() as $release) {
            unset($release);
            break;
        }

        $secondClient = new PagedClientStub(pages: 3);
        $all = self::names(self::createRepository($secondClient, self::registry($storage)));

        Assert::same($firstClient->requestedPages(), [1]);
        Assert::same($secondClient->requestedPages(), [2, 3]);
        Assert::same(\count($all), 300);
    }

    #[Test]
    public function tailIsLoadedFromInsideAPageWhenTheStoredCountIsNotPageAligned(): void
    {
        $storage = new InMemoryRegistryStorage();
        $storage->save((new RepositoryRecord(new RepositoryId(GitLabRepository::TYPE, 'group/project'), checkedAt: \time()))->withHead(\array_map(
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
    public function nameIsTheGroupAndProject(): void
    {
        Assert::same(self::createRepository(new PagedClientStub(pages: 1))->getName(), 'group/project');
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
        $registry = new ScriptedRegistryStub([new \RuntimeException('missing project')]);
        $repository = self::createRepository(new PagedClientStub(pages: 1), $registry);

        Expect::exception(\RuntimeException::class)->withMessage('missing project');

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
    ): GitLabRepository {
        $logger = new Logger();
        $httpFactory = new NyholmFactoryImpl($logger);
        $api = new RepositoryApi(
            new Client($httpFactory, $client, new GitLabConfig()),
            $httpFactory,
            'group/project',
        );

        return new GitLabRepository($api, 'group/project', $logger, $registry);
    }

    private static function registry(InMemoryRegistryStorage $storage): StoredVersionRegistry
    {
        return new StoredVersionRegistry($storage, 600, new Logger());
    }

    /**
     * @return list<string>
     */
    private static function names(GitLabRepository $repository): array
    {
        return \array_map(
            static fn(ReleaseInterface $release): string => $release->getName(),
            \iterator_to_array($repository->getReleases(), false),
        );
    }
}
