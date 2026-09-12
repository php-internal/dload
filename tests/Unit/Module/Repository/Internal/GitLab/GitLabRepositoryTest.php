<?php

declare(strict_types=1);

namespace Internal\DLoad\Tests\Unit\Module\Repository\Internal\GitLab;

use Internal\DLoad\Module\Cache\Internal\NullResponseCache;
use Internal\DLoad\Module\Config\Schema\GitLab as GitLabConfig;
use Internal\DLoad\Module\HttpClient\Internal\NyholmFactoryImpl;
use Internal\DLoad\Module\Repository\Internal\GitLab\Api\Client;
use Internal\DLoad\Module\Repository\Internal\GitLab\Api\RepositoryApi;
use Internal\DLoad\Module\Repository\Internal\GitLab\GitLabRepository;
use Internal\DLoad\Service\Logger;
use Internal\DLoad\Tests\Unit\Module\Repository\Internal\GitLab\Stub\PagedClientStub;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Test;

#[Covers(GitLabRepository::class)]
final class GitLabRepositoryTest
{
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
    public function releasesAreRequestedAHundredPerPage(): void
    {
        $client = new PagedClientStub(pages: 1, releasesPerPage: 2);
        $repository = self::createRepository($client);

        \iterator_to_array($repository->getReleases(), false);

        Assert::same($client->requests, ['page=1&per_page=100']);
    }

    private static function createRepository(PagedClientStub $client): GitLabRepository
    {
        $logger = new Logger();
        $httpFactory = new NyholmFactoryImpl($logger);
        $api = new RepositoryApi(
            new Client($httpFactory, $client, new GitLabConfig()),
            $httpFactory,
            'group/project',
            new NullResponseCache(),
        );

        return new GitLabRepository($api, 'group/project', $logger);
    }
}
