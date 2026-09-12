<?php

declare(strict_types=1);

namespace Internal\DLoad\Tests\Unit\Module\Repository\Internal\GitHub;

use Internal\DLoad\Module\Cache\Internal\FileResponseCache;
use Internal\DLoad\Module\Cache\Internal\NullResponseCache;
use Internal\DLoad\Module\Cache\ResponseCache;
use Internal\DLoad\Module\Config\Schema\GitHub as GitHubConfig;
use Internal\DLoad\Module\HttpClient\Internal\NyholmFactoryImpl;
use Internal\DLoad\Module\Repository\Internal\GitHub\Api\Client;
use Internal\DLoad\Module\Repository\Internal\GitHub\Api\RepositoryApi;
use Internal\DLoad\Module\Repository\Internal\GitHub\GitHubRepository;
use Internal\DLoad\Service\Logger;
use Internal\DLoad\Tests\Unit\Module\Repository\Internal\GitHub\Stub\PagedClientStub;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Lifecycle\AfterTest;
use Testo\Lifecycle\BeforeTest;
use Testo\Test;

#[Covers(GitHubRepository::class)]
#[Covers(RepositoryApi::class)]
final class GitHubRepositoryTest
{
    private string $cacheDirectory;

    #[Test]
    public function everyReleasesPageIsRequestedOnce(): void
    {
        $client = new PagedClientStub(pages: 3, releasesPerPage: 2);
        $repository = self::createRepository($client);

        $releases = \iterator_to_array($repository->getReleases(), false);

        Assert::same(\count($releases), 6);
        Assert::same($client->requestedPages(), [1, 2, 3]);
    }

    #[Test]
    public function pagesAreLoadedOnlyWhenNeeded(): void
    {
        $client = new PagedClientStub(pages: 3, releasesPerPage: 2);
        $repository = self::createRepository($client);

        foreach ($repository->getReleases() as $release) {
            unset($release);
            break;
        }

        Assert::same($client->requestedPages(), [1]);
    }

    #[Test]
    public function releasesAreRequestedAHundredPerPage(): void
    {
        $client = new PagedClientStub(pages: 1, releasesPerPage: 2);
        $repository = self::createRepository($client);

        \iterator_to_array($repository->getReleases(), false);

        Assert::same($client->requests, ['page=1&per_page=100']);
    }

    #[Test]
    public function cachedListingsCostNoRequestsOnTheNextRun(): void
    {
        $firstClient = new PagedClientStub(pages: 2, releasesPerPage: 2);
        $firstRun = \iterator_to_array(self::createRepository($firstClient, $this->cache())->getReleases(), false);

        $secondClient = new PagedClientStub(pages: 2, releasesPerPage: 2);
        $secondRun = \iterator_to_array(self::createRepository($secondClient, $this->cache())->getReleases(), false);

        Assert::same($firstClient->requestedPages(), [1, 2]);
        Assert::same($secondClient->requestedPages(), []);
        Assert::same(\count($firstRun), 4);
        Assert::same(\count($secondRun), 4);
    }

    #[BeforeTest]
    protected function prepare(): void
    {
        $this->cacheDirectory = \sys_get_temp_dir() . '/dload-github-cache-' . \bin2hex(\random_bytes(6));
    }

    #[AfterTest]
    protected function cleanup(): void
    {
        if (!\is_dir($this->cacheDirectory)) {
            return;
        }

        foreach (\glob($this->cacheDirectory . '/*') as $file) {
            \is_file($file) and \unlink($file);
        }

        \rmdir($this->cacheDirectory);
    }

    private static function createRepository(
        PagedClientStub $client,
        ResponseCache $cache = new NullResponseCache(),
    ): GitHubRepository {
        $logger = new Logger();
        $httpFactory = new NyholmFactoryImpl($logger);
        $api = new RepositoryApi(
            new Client($httpFactory, $client, new GitHubConfig()),
            $httpFactory,
            'owner',
            'repo',
            $logger,
            $cache,
        );

        return new GitHubRepository($api, 'owner', 'repo', $logger);
    }

    private function cache(): FileResponseCache
    {
        return new FileResponseCache($this->cacheDirectory, 600, new Logger());
    }
}
