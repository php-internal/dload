<?php

declare(strict_types=1);

namespace Internal\DLoad\Tests\Unit\Module\Repository\Internal\GitLab;

use Internal\DLoad\Module\Config\Schema\GitLab as GitLabConfig;
use Internal\DLoad\Module\HttpClient\Internal\NyholmFactoryImpl;
use Internal\DLoad\Module\Registry\Internal\PassThroughRegistry;
use Internal\DLoad\Module\Registry\Internal\StoredVersionRegistry;
use Internal\DLoad\Module\Registry\VersionRegistry;
use Internal\DLoad\Module\Repository\Internal\GitLab\Api\Client;
use Internal\DLoad\Module\Repository\Internal\GitLab\Api\RepositoryApi;
use Internal\DLoad\Module\Repository\Internal\GitLab\GitLabReleaseSource;
use Internal\DLoad\Module\Repository\Internal\GitLab\GitLabRepository;
use Internal\DLoad\Service\Logger;
use Internal\DLoad\Tests\Unit\Module\Registry\Stub\InMemoryRegistryStorage;
use Internal\DLoad\Tests\Unit\Module\Repository\Internal\GitLab\Stub\PagedClientStub;
use Testo\Assert;
use Testo\Codecov\Covers;
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
}
