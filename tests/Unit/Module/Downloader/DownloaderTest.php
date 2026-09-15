<?php

declare(strict_types=1);

namespace Internal\DLoad\Tests\Unit\Module\Downloader;

use Internal\DLoad\Module\Archive\ArchiveFactory;
use Internal\DLoad\Module\Common\Architecture;
use Internal\DLoad\Module\Common\FileSystem\FS;
use Internal\DLoad\Module\Common\OperatingSystem;
use Internal\DLoad\Module\Common\Stability;
use Internal\DLoad\Module\Config\Schema\Action\Download as DownloadConfig;
use Internal\DLoad\Module\Config\Schema\Action\Type;
use Internal\DLoad\Module\Config\Schema\Downloader as DownloaderConfig;
use Internal\DLoad\Module\Config\Schema\Embed\Software;
use Internal\DLoad\Module\Downloader\Downloader;
use Internal\DLoad\Module\Downloader\Exception\DownloadFailed;
use Internal\DLoad\Module\Downloader\Task\DownloadResult;
use Internal\DLoad\Module\Repository\Collection\ReleasesCollection;
use Internal\DLoad\Module\Repository\Exception\RateLimitException;
use Internal\DLoad\Module\Repository\Repository;
use Internal\DLoad\Module\Repository\RepositoryProvider;
use Internal\DLoad\Module\Version\Version;
use Internal\DLoad\Service\Logger;
use Internal\DLoad\Tests\Unit\Module\Downloader\Stub\ConfigurableAssetStub;
use Internal\DLoad\Tests\Unit\Module\Downloader\Stub\GoneAssetStub;
use Internal\DLoad\Tests\Unit\Module\Downloader\Stub\SequenceRepositoryFactoryStub;
use Internal\DLoad\Tests\Unit\Module\Downloader\Stub\ThrowingRepositoryStub;
use Internal\DLoad\Tests\Unit\Module\Registry\Stub\RecordingRegistry;
use Internal\DLoad\Tests\Unit\Module\Repository\Stub\AssetStub;
use Internal\DLoad\Tests\Unit\Module\Repository\Stub\ReleaseStub;
use Internal\DLoad\Tests\Unit\Module\Repository\Stub\RepositoryStub;
use Internal\Path;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Data\DataProvider;
use Testo\Expect;
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

    public static function provideGradualStrategies(): \Generator
    {
        yield 'OS and architecture' => [OperatingSystem::Linux, Architecture::X86_64];
        yield 'OS only' => [OperatingSystem::Linux, null];
        yield 'architecture only' => [null, Architecture::X86_64];
    }

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
    public function registryIdComesFromTheRepositoryNotTheConfiguredUri(): void
    {
        // The factory reduces a full URL to the path; the registry must key the record the same way
        $repository = new RepositoryStub('owner/repo');
        $gone = self::release($repository, 'v2.0.0', assets: false);
        $alive = self::release($repository, 'v1.9.0', assets: true);
        $repository = new RepositoryStub('owner/repo', ReleasesCollection::create([$gone, $alive]));

        $this->download([$repository], uri: 'https://github.com/Owner/Repo');

        Assert::same($this->registry->attached, [['rr', 'github:owner/repo']]);
        Assert::same($this->registry->forgotten, [['github:owner/repo', 'v2.0.0']]);
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

    #[Test]
    public function releaseFailingForOtherReasonsIsNotForgotten(): void
    {
        $repository = new RepositoryStub('owner/repo');
        $broken = new ReleaseStub($repository, 'v2.0.0', Version::fromVersionString('v2.0.0'));
        $broken->setAssets([
            new GoneAssetStub($broken, 'rr-linux-amd64.tar.gz'),
            // Every asset fails, but a network error says nothing about the release being gone
            new GoneAssetStub($broken, 'rr-linux-amd64.zip', new \RuntimeException('Connection reset by peer')),
        ]);
        $factory = new SequenceRepositoryFactoryStub([
            new RepositoryStub('owner/repo', ReleasesCollection::create([$broken])),
        ]);

        try {
            $this->download($factory);
            Assert::fail('DownloadFailed is expected when no asset could be downloaded.');
        } catch (DownloadFailed) {
            Assert::same($this->registry->forgotten, []);
            Assert::same($factory->created, 1);
        }
    }

    #[DataProvider('provideGradualStrategies')]
    #[Test]
    public function gradualFilteringSelectsAssetByEachStrategy(?OperatingSystem $os, ?Architecture $arch): void
    {
        $repository = $this->repoWithAsset('rr-linux-amd64.tar.gz', $os, $arch);

        $result = $this->run([$repository], self::software());

        Assert::same($result->version->string, 'v1.0.0');
    }

    #[Test]
    public function gradualFilteringTriesEveryStrategyBeforeFailing(): void
    {
        // One asset matches OS+arch, OS-only and arch-only, so every strategy selects it; its
        // generic download error is not "gone", so the search moves on to the next, wider strategy.
        $repository = $this->repoWithAsset(
            'rr-linux-amd64.tar.gz',
            OperatingSystem::Linux,
            Architecture::X86_64,
            new \RuntimeException('boom'),
        );

        try {
            $this->run([$repository], self::software());
            Assert::fail('DownloadFailed is expected when no strategy can download the asset.');
        } catch (DownloadFailed) {
            // A plain error must not forget the release from the registry
            Assert::same($this->registry->forgotten, []);
        }
    }

    #[Test]
    public function strictFilteringSelectsAssetWhenBinaryConfigured(): void
    {
        $repository = $this->repoWithAsset('rr-linux-amd64.tar.gz', OperatingSystem::Linux, Architecture::X86_64);

        $result = $this->run([$repository], self::software(binary: true));

        Assert::same($result->version->string, 'v1.0.0');
    }

    #[Test]
    public function strictFilteringFailsWhenNoAssetMatchesAllCriteria(): void
    {
        // Binary config forces strict filtering; a Windows/arm64 asset matches neither OS nor arch
        $repository = $this->repoWithAsset('rr-windows-arm64.zip', OperatingSystem::Windows, Architecture::ARM_64);

        try {
            $this->run([$repository], self::software(binary: true));
            Assert::fail('DownloadFailed is expected when strict filtering matches nothing.');
        } catch (DownloadFailed $e) {
            Assert::string($e->report)->contains('no asset matches OS');
        }
    }

    #[Test]
    public function versionConstraintSelectsMatchingRelease(): void
    {
        $repository = new RepositoryStub('owner/repo');
        $v1 = $this->assetRelease($repository, 'v1.5.0');
        $v2 = $this->assetRelease($repository, 'v2.0.0');
        $repository = new RepositoryStub('owner/repo', ReleasesCollection::create([$v1, $v2]));

        $config = DownloadConfig::fromSoftwareId('rr');
        $config->version = '^1.0';

        $result = $this->run([$repository], self::software(), $config);

        Assert::same($result->version->string, 'v1.5.0');
    }

    #[Test]
    public function noRelevantReleaseReportsTheAvailableOnes(): void
    {
        // More releases than the report lists: fetchReleaseNames must stop at its limit
        $repository = new RepositoryStub('owner/repo');
        $releases = [];
        for ($i = 1; $i <= 12; ++$i) {
            $releases[] = $this->assetRelease($repository, 'v1.0.' . $i);
        }
        $repository = new RepositoryStub('owner/repo', ReleasesCollection::create($releases));

        $config = DownloadConfig::fromSoftwareId('rr');
        $config->version = '^9.9';

        try {
            $this->run([$repository], self::software(), $config);
            Assert::fail('DownloadFailed is expected when no release satisfies the constraint.');
        } catch (DownloadFailed $e) {
            Assert::string($e->report)->contains('Releases available in the repository');
            Assert::string($e->report)->contains('v1.0.1');
        }
    }

    #[Test]
    public function pharTypeWithoutPharAssetReportsThePharRequirement(): void
    {
        $repository = $this->repoWithAsset('rr-linux-amd64.tar.gz', OperatingSystem::Linux, Architecture::X86_64);

        $config = DownloadConfig::fromSoftwareId('rr');
        $config->type = Type::Phar;

        try {
            $this->run([$repository], self::software(), $config);
            Assert::fail('DownloadFailed is expected when no phar asset is present.');
        } catch (DownloadFailed $e) {
            Assert::string($e->report)->contains('phar');
        }
    }

    #[Test]
    public function archiveTypeWithoutArchiveAssetReportsTheArchiveRequirement(): void
    {
        $repository = $this->repoWithAsset('rr-linux-amd64', OperatingSystem::Linux, Architecture::X86_64);

        $config = DownloadConfig::fromSoftwareId('rr');
        $config->type = Type::Archive;

        try {
            $this->run([$repository], self::software(), $config);
            Assert::fail('DownloadFailed is expected when no archive asset is present.');
        } catch (DownloadFailed $e) {
            Assert::string($e->report)->contains('archive extensions');
        }
    }

    #[Test]
    public function rateLimitedRepositoryFallsBackToTheNextOne(): void
    {
        $limited = $this->repoWithAsset(
            'rr-linux-amd64.tar.gz',
            OperatingSystem::Linux,
            Architecture::X86_64,
            new RateLimitException('API rate limit exceeded', 'owner/repo'),
        );
        $working = $this->repoWithAsset(
            'rr-linux-amd64.tar.gz',
            OperatingSystem::Linux,
            Architecture::X86_64,
            tag: 'v3.0.0',
        );

        $result = $this->run(
            new SequenceRepositoryFactoryStub([$limited, $working]),
            self::software(repositories: 2),
        );

        Assert::same($result->version->string, 'v3.0.0');
    }

    #[Test]
    public function unexpectedErrorFromRepositoryIsRethrown(): void
    {
        $repository = new ThrowingRepositoryStub('owner/repo', new \RuntimeException('unexpected failure'));

        Expect::exception(\RuntimeException::class)->withMessage('unexpected failure');

        $this->run([$repository], self::software());
    }

    #[Test]
    public function tmpDirPointingAtAFileIsRejected(): void
    {
        \is_dir($this->tempDir) or \mkdir($this->tempDir, recursive: true);
        $file = $this->tempDir . '/not-a-directory';
        \file_put_contents($file, 'x');

        $config = new DownloaderConfig();
        $config->tmpDir = $file;
        $downloader = $this->makeDownloader(new SequenceRepositoryFactoryStub([]), $config);

        Expect::exception(\LogicException::class);

        $downloader->download(self::software(), DownloadConfig::fromSoftwareId('rr'), static fn(): null => null);
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
     * @param positive-int $repositories Number of repository configs on the software definition.
     */
    private static function software(bool $binary = false, int $repositories = 1): Software
    {
        $repos = [];
        for ($i = 1; $i <= $repositories; ++$i) {
            $repos[] = ['type' => 'github', 'uri' => $i === 1 ? 'owner/repo' : 'owner/repo' . $i];
        }

        $data = ['name' => 'rr', 'repositories' => $repos];
        $binary and $data['binary'] = ['name' => 'rr'];

        return Software::fromArray($data);
    }

    /**
     * @param list<Repository>|SequenceRepositoryFactoryStub $repositories
     * @param non-empty-string $uri Repository URI as written in the config.
     */
    private function download(array|SequenceRepositoryFactoryStub $repositories, string $uri = 'owner/repo'): DownloadResult
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
            'repositories' => [['type' => 'github', 'uri' => $uri]],
        ]);
        $task = $downloader->download($software, DownloadConfig::fromSoftwareId('rr'), static fn(): null => null);

        /** @var DownloadResult */
        return await(($task->handler)());
    }

    /**
     * @param list<Repository>|SequenceRepositoryFactoryStub $repositories
     */
    private function run(
        array|SequenceRepositoryFactoryStub $repositories,
        Software $software,
        ?DownloadConfig $config = null,
    ): DownloadResult {
        $factory = $repositories instanceof SequenceRepositoryFactoryStub
            ? $repositories
            : new SequenceRepositoryFactoryStub($repositories);

        $downloaderConfig = new DownloaderConfig();
        $downloaderConfig->tmpDir = $this->tempDir;

        $task = $this->makeDownloader($factory, $downloaderConfig)->download(
            $software,
            $config ?? DownloadConfig::fromSoftwareId('rr'),
            static fn(): null => null,
        );

        /** @var DownloadResult */
        return await(($task->handler)());
    }

    private function makeDownloader(SequenceRepositoryFactoryStub $factory, DownloaderConfig $config): Downloader
    {
        return new Downloader(
            config: $config,
            logger: new Logger(),
            repositoryProvider: (new RepositoryProvider())->addRepositoryFactory($factory),
            architecture: Architecture::tryFromString('amd64') ?? throw new \LogicException(),
            operatingSystem: OperatingSystem::tryFromString('linux') ?? throw new \LogicException(),
            stability: Stability::Stable,
            archiveService: new ArchiveFactory(),
            registry: $this->registry,
        );
    }

    /**
     * @param non-empty-string $name Asset file name.
     * @param non-empty-string $tag Release tag.
     */
    private function repoWithAsset(
        string $name,
        ?OperatingSystem $os = null,
        ?Architecture $arch = null,
        ?\Throwable $failure = null,
        string $tag = 'v1.0.0',
    ): RepositoryStub {
        $repository = new RepositoryStub('owner/repo');
        $release = new ReleaseStub($repository, $tag, Version::fromVersionString($tag));
        $release->setAssets([new ConfigurableAssetStub($release, $name, $os, $arch, $failure)]);

        return new RepositoryStub('owner/repo', ReleasesCollection::create([$release]));
    }

    /**
     * @param non-empty-string $tag Release tag; the asset always matches the Linux/amd64 target.
     */
    private function assetRelease(RepositoryStub $repository, string $tag): ReleaseStub
    {
        $release = new ReleaseStub($repository, $tag, Version::fromVersionString($tag));
        $release->setAssets([
            new ConfigurableAssetStub($release, 'rr-linux-amd64.tar.gz', OperatingSystem::Linux, Architecture::X86_64),
        ]);

        return $release;
    }
}
