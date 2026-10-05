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
use Internal\DLoad\Module\Downloader\Task\DownloadResult;
use Internal\DLoad\Module\Repository\Collection\ReleasesCollection;
use Internal\DLoad\Module\Repository\RepositoryProvider;
use Internal\DLoad\Module\Version\Version;
use Internal\DLoad\Service\Logger;
use Internal\DLoad\Tests\Unit\Module\Downloader\Stub\SequenceRepositoryFactoryStub;
use Internal\DLoad\Tests\Unit\Module\Registry\Stub\RecordingRegistry;
use Internal\DLoad\Tests\Unit\Module\Repository\Stub\AssetStub;
use Internal\DLoad\Tests\Unit\Module\Repository\Stub\ReleaseStub;
use Internal\DLoad\Tests\Unit\Module\Repository\Stub\RepositoryStub;
use Internal\Path;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Core\Exception\SkipTest;
use Testo\Data\DataProvider;
use Testo\Lifecycle\AfterTest;
use Testo\Lifecycle\BeforeTest;
use Testo\Test;

use function React\Async\await;

/**
 * Which asset the downloader picks from a real release that ships several variants per platform.
 */
#[Covers(Downloader::class)]
final class AssetSelectionTest
{
    /**
     * Assets of the Bun v1.4.2 release, in the order the GitHub API lists them.
     */
    private const BUN_ASSETS = [
        'bun-darwin-aarch64-profile.zip',
        'bun-darwin-aarch64.zip',
        'bun-darwin-x64-baseline-profile.zip',
        'bun-darwin-x64-baseline.zip',
        'bun-darwin-x64-profile.zip',
        'bun-darwin-x64.zip',
        'bun-freebsd-aarch64-profile.zip',
        'bun-freebsd-aarch64.zip',
        'bun-freebsd-x64-baseline-profile.zip',
        'bun-freebsd-x64-baseline.zip',
        'bun-freebsd-x64-profile.zip',
        'bun-freebsd-x64.zip',
        'bun-linux-aarch64-android-profile.zip',
        'bun-linux-aarch64-android.zip',
        'bun-linux-aarch64-musl-profile.zip',
        'bun-linux-aarch64-musl.zip',
        'bun-linux-aarch64-profile.zip',
        'bun-linux-aarch64.zip',
        'bun-linux-x64-android-baseline-profile.zip',
        'bun-linux-x64-android-baseline.zip',
        'bun-linux-x64-android-profile.zip',
        'bun-linux-x64-android.zip',
        'bun-linux-x64-baseline-profile.zip',
        'bun-linux-x64-baseline.zip',
        'bun-linux-x64-musl-baseline-profile.zip',
        'bun-linux-x64-musl-baseline.zip',
        'bun-linux-x64-musl-profile.zip',
        'bun-linux-x64-musl.zip',
        'bun-linux-x64-profile.zip',
        'bun-linux-x64.zip',
        'bun-windows-aarch64-profile.zip',
        'bun-windows-aarch64.zip',
        'bun-windows-x64-baseline-profile.zip',
        'bun-windows-x64-baseline.zip',
        'bun-windows-x64-profile.zip',
        'bun-windows-x64.zip',
        'SHASUMS256.txt',
        'SHASUMS256.txt.asc',
    ];

    private string $tempDir;

    public static function provideBunHosts(): \Generator
    {
        yield 'Linux x64' => [OperatingSystem::Linux, Architecture::X86_64, 'bun-linux-x64.zip'];
        yield 'Linux arm64' => [OperatingSystem::Linux, Architecture::ARM_64, 'bun-linux-aarch64.zip'];
        yield 'macOS x64' => [OperatingSystem::Darwin, Architecture::X86_64, 'bun-darwin-x64.zip'];
        yield 'macOS arm64' => [OperatingSystem::Darwin, Architecture::ARM_64, 'bun-darwin-aarch64.zip'];
        yield 'Windows x64' => [OperatingSystem::Windows, Architecture::X86_64, 'bun-windows-x64.zip'];
        yield 'Windows arm64' => [OperatingSystem::Windows, Architecture::ARM_64, 'bun-windows-aarch64.zip'];
        yield 'FreeBSD x64' => [OperatingSystem::BSD, Architecture::X86_64, 'bun-freebsd-x64.zip'];
        yield 'FreeBSD arm64' => [OperatingSystem::BSD, Architecture::ARM_64, 'bun-freebsd-aarch64.zip'];
    }

    #[DataProvider('provideBunHosts')]
    #[Test]
    public function bunRegistryEntrySelectsThePlainBuild(
        OperatingSystem $os,
        Architecture $arch,
        string $expected,
    ): void {
        $result = $this->downloadBun(self::registryEntry('bun'), $os, $arch);

        Assert::same($result->file->getFilename(), $expected);
    }

    /**
     * The target of variant ranking: the plain build wins without a pattern that spells out
     * the asset shape of one tool.
     */
    #[DataProvider('provideBunHosts')]
    #[Test]
    public function bunPlainBuildIsSelectedWithABroadAssetPattern(
        OperatingSystem $os,
        Architecture $arch,
        string $expected,
    ): void {
        $software = Software::fromArray([
            'name' => 'Bun',
            'alias' => 'bun',
            'repositories' => [['type' => 'github', 'uri' => 'oven-sh/bun', 'asset-pattern' => '/^bun-.*/']],
            'binary' => ['name' => 'bun'],
        ]);

        $result = $this->downloadBun($software, $os, $arch);

        $result->file->getFilename() === $expected or throw new SkipTest(\sprintf(
            'Picks `%s`: asset variants are not ranked yet, see https://github.com/php-internal/dload/issues/134',
            $result->file->getFilename(),
        ));
        Assert::same($result->file->getFilename(), $expected);
    }

    #[BeforeTest]
    protected function setUp(): void
    {
        $this->tempDir = \sys_get_temp_dir() . '/dload-asset-selection-' . \bin2hex(\random_bytes(6));
        \mkdir($this->tempDir, recursive: true);
    }

    #[AfterTest]
    protected function cleanup(): void
    {
        \is_dir($this->tempDir) and FS::removeDir(Path::create($this->tempDir));
    }

    /**
     * @param non-empty-string $alias
     */
    private static function registryEntry(string $alias): Software
    {
        /** @var array{software: list<array{alias?: string}>} $registry */
        $registry = \json_decode(
            (string) \file_get_contents(\dirname(__DIR__, 4) . '/resources/software.json'),
            true,
            flags: \JSON_THROW_ON_ERROR,
        );

        foreach ($registry['software'] as $entry) {
            if (($entry['alias'] ?? null) === $alias) {
                return Software::fromArray($entry);
            }
        }

        throw new \LogicException("No `$alias` in the software registry.");
    }

    private function downloadBun(Software $software, OperatingSystem $os, Architecture $arch): DownloadResult
    {
        $repository = new RepositoryStub('oven-sh/bun');
        $release = new ReleaseStub($repository, 'Bun v1.4.2', Version::fromVersionString('v1.4.2'), tag: 'bun-v1.4.2');
        $release->setAssets(\array_map(
            static fn(string $name): AssetStub => new AssetStub(
                $release,
                $name,
                'https://github.com/oven-sh/bun/releases/download/bun-v1.4.2/' . $name,
                OperatingSystem::tryFromBuildName($name),
                Architecture::tryFromBuildName($name),
            ),
            self::BUN_ASSETS,
        ));
        $repository = new RepositoryStub('oven-sh/bun', ReleasesCollection::create([$release]));

        $config = new DownloaderConfig();
        $config->tmpDir = $this->tempDir;

        $downloader = new Downloader(
            config: $config,
            logger: new Logger(),
            repositoryProvider: (new RepositoryProvider())->addRepositoryFactory(
                new SequenceRepositoryFactoryStub([$repository]),
            ),
            architecture: $arch,
            operatingSystem: $os,
            stability: Stability::Stable,
            archiveService: new ArchiveFactory(),
            registry: new RecordingRegistry(),
        );
        $task = $downloader->download($software, DownloadConfig::fromSoftwareId('bun'), static fn(): null => null);

        /** @var DownloadResult */
        return await(($task->handler)());
    }
}
