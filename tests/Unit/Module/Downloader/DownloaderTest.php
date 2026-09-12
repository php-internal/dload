<?php

declare(strict_types=1);

namespace Internal\DLoad\Tests\Unit\Module\Downloader;

use Internal\DLoad\Module\Archive\ArchiveFactory;
use Internal\DLoad\Module\Common\Architecture;
use Internal\DLoad\Module\Common\FileSystem\FS;
use Internal\DLoad\Module\Common\OperatingSystem;
use Internal\DLoad\Module\Common\Stability;
use Internal\DLoad\Module\Config\Schema\Action\Download as DownloadConfig;
use Internal\DLoad\Module\Config\Schema\Downloader as DownloaderConfig;
use Internal\DLoad\Module\Config\Schema\Embed\Software;
use Internal\DLoad\Module\Downloader\Downloader;
use Internal\DLoad\Module\Downloader\Exception\DownloadFailed;
use Internal\DLoad\Module\Downloader\Task\DownloadResult;
use Internal\DLoad\Module\Repository\Collection\ReleasesCollection;
use Internal\DLoad\Module\Repository\Repository;
use Internal\DLoad\Module\Repository\RepositoryProvider;
use Internal\DLoad\Module\Version\Version;
use Internal\DLoad\Service\Logger;
use Internal\DLoad\Tests\Unit\Module\Downloader\Stub\GoneAssetStub;
use Internal\DLoad\Tests\Unit\Module\Downloader\Stub\SequenceRepositoryFactoryStub;
use Internal\DLoad\Tests\Unit\Module\Registry\Stub\RecordingRegistry;
use Internal\DLoad\Tests\Unit\Module\Repository\Stub\AssetStub;
use Internal\DLoad\Tests\Unit\Module\Repository\Stub\ReleaseStub;
use Internal\DLoad\Tests\Unit\Module\Repository\Stub\RepositoryStub;
use Internal\Path;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Lifecycle\AfterTest;
use Testo\Lifecycle\BeforeTest;
use Testo\Test;

use function React\Async\await;

/**
 * How the downloader reacts to a release list that is outdated: a release was deleted upstream
 * after the version registry stored it, so its assets answer "not found".
 */
#[Covers(Downloader::class)]
final class DownloaderTest
{
    private string $tempDir;
    private RecordingRegistry $registry;

    #[Test]
    public function deletedReleaseIsForgottenAndTheNextOneIsUsed(): void
    {
        $repository = new RepositoryStub('owner/repo');
        $gone = self::release($repository, 'v2.0.0', assets: false);
        $alive = self::release($repository, 'v1.9.0', assets: true);
        $repository = new RepositoryStub('owner/repo', ReleasesCollection::create([$gone, $alive]));

        $result = $this->download([$repository]);

        Assert::same($result->version->string, 'v1.9.0');
        Assert::same($this->registry->forgotten, [['github:owner/repo', 'v2.0.0']]);
        Assert::same($this->registry->attached, [['rr', 'github:owner/repo']]);
    }

    #[Test]
    public function outdatedListIsFetchedAgainWhenNothingIsLeft(): void
    {
        // The stored list knows only the deleted release; a fresh list has its replacement
        $stale = new RepositoryStub('owner/repo');
        $stale = new RepositoryStub('owner/repo', ReleasesCollection::create([
            self::release($stale, 'v2.0.0', assets: false),
        ]));
        $fresh = new RepositoryStub('owner/repo');
        $fresh = new RepositoryStub('owner/repo', ReleasesCollection::create([
            self::release($fresh, 'v2.0.1', assets: true),
        ]));
        $factory = new SequenceRepositoryFactoryStub([$stale, $fresh]);

        $result = $this->download($factory);

        Assert::same($result->version->string, 'v2.0.1');
        Assert::same($factory->created, 2);
        Assert::same($this->registry->forgotten, [['github:owner/repo', 'v2.0.0']]);
    }

    #[Test]
    public function outdatedListIsFetchedAgainOnlyOnce(): void
    {
        $stale = new RepositoryStub('owner/repo');
        $stale = new RepositoryStub('owner/repo', ReleasesCollection::create([
            self::release($stale, 'v2.0.0', assets: false),
        ]));
        $factory = new SequenceRepositoryFactoryStub([$stale]);

        try {
            $this->download($factory);
            Assert::fail('DownloadFailed is expected when the fresh list has nothing suitable either.');
        } catch (DownloadFailed $e) {
            Assert::same($factory->created, 2);
            Assert::string($e->report)->contains('no longer available');
        }
    }

    #[Test]
    public function releaseWithOtherFailuresIsNotForgotten(): void
    {
        $repository = new RepositoryStub('owner/repo');
        $broken = new ReleaseStub($repository, 'v2.0.0', Version::fromVersionString('v2.0.0'));
        $broken->setAssets([
            new GoneAssetStub($broken, 'rr-linux-amd64.tar.gz'),
            // A working asset: the release is not gone, the first asset just was
            new AssetStub($broken, 'rr-linux-amd64.zip', 'https://x/rr.zip'),
        ]);
        $repository = new RepositoryStub('owner/repo', ReleasesCollection::create([$broken]));

        $result = $this->download([$repository]);

        Assert::same($result->version->string, 'v2.0.0');
        Assert::same($this->registry->forgotten, []);
    }

    #[BeforeTest]
    protected function prepare(): void
    {
        $this->tempDir = \sys_get_temp_dir() . '/dload-downloader-' . \bin2hex(\random_bytes(6));
        $this->registry = new RecordingRegistry();
    }

    #[AfterTest]
    protected function cleanup(): void
    {
        \is_dir($this->tempDir) and FS::removeDir(Path::create($this->tempDir));
    }

    /**
     * @param non-empty-string $tag
     * @param bool $assets `true` for a downloadable asset, `false` for one that is gone.
     */
    private static function release(RepositoryStub $repository, string $tag, bool $assets): ReleaseStub
    {
        $release = new ReleaseStub($repository, $tag, Version::fromVersionString($tag));
        $release->setAssets([
            $assets
                ? new AssetStub($release, 'rr-linux-amd64.tar.gz', 'https://x/' . $tag . '/rr.tar.gz')
                : new GoneAssetStub($release, 'rr-linux-amd64.tar.gz'),
        ]);

        return $release;
    }

    /**
     * @param list<Repository>|SequenceRepositoryFactoryStub $repositories
     */
    private function download(array|SequenceRepositoryFactoryStub $repositories): DownloadResult
    {
        $factory = $repositories instanceof SequenceRepositoryFactoryStub
            ? $repositories
            : new SequenceRepositoryFactoryStub($repositories);

        $config = new DownloaderConfig();
        $config->tmpDir = $this->tempDir;

        $downloader = new Downloader(
            config: $config,
            logger: new Logger(),
            repositoryProvider: (new RepositoryProvider())->addRepositoryFactory($factory),
            architecture: Architecture::tryFromString('amd64') ?? throw new \LogicException(),
            operatingSystem: OperatingSystem::tryFromString('linux') ?? throw new \LogicException(),
            stability: Stability::Stable,
            archiveService: new ArchiveFactory(),
            registry: $this->registry,
        );

        $software = Software::fromArray([
            'name' => 'rr',
            'repositories' => [['type' => 'github', 'uri' => 'owner/repo']],
        ]);
        $task = $downloader->download($software, DownloadConfig::fromSoftwareId('rr'), static fn(): null => null);

        /** @var DownloadResult */
        return await(($task->handler)());
    }
}
