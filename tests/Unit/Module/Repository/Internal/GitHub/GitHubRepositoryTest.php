<?php

declare(strict_types=1);

namespace Internal\DLoad\Tests\Unit\Module\Repository\Internal\GitHub;

use Internal\DLoad\Module\Config\Schema\GitHub as GitHubConfig;
use Internal\DLoad\Module\HttpClient\Internal\NyholmFactoryImpl;
use Internal\DLoad\Module\Registry\Internal\PassThroughRegistry;
use Internal\DLoad\Module\Registry\Internal\StoredVersionRegistry;
use Internal\DLoad\Module\Registry\VersionRegistry;
use Internal\DLoad\Module\Repository\Internal\GitHub\Api\Client;
use Internal\DLoad\Module\Repository\Internal\GitHub\Api\RepositoryApi;
use Internal\DLoad\Module\Repository\Internal\GitHub\GitHubReleaseSource;
use Internal\DLoad\Module\Repository\Internal\GitHub\GitHubRepository;
use Internal\DLoad\Module\Repository\ReleaseInterface;
use Internal\DLoad\Service\Logger;
use Internal\DLoad\Tests\Unit\Module\Registry\Stub\InMemoryRegistryStorage;
use Internal\DLoad\Tests\Unit\Module\Repository\Internal\GitHub\Stub\PagedClientStub;
use Testo\Assert;
use Testo\Codecov\Covers;
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

    private static function createRepository(
        PagedClientStub $client,
        VersionRegistry $registry = new PassThroughRegistry(),
    ): GitHubRepository {
        $logger = new Logger();
        $httpFactory = new NyholmFactoryImpl($logger);
        $api = new RepositoryApi(
            new Client($httpFactory, $client, new GitHubConfig()),
            $httpFactory,
            'owner',
            'repo',
            $logger,
        );

        return new GitHubRepository($api, 'owner', 'repo', $logger, $registry);
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
