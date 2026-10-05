<?php

declare(strict_types=1);

namespace Internal\DLoad\Tests\Unit\Module\Downloader;

use Internal\DLoad\Module\Archive\ArchiveFactory;
use Internal\DLoad\Module\Common\Architecture;
use Internal\DLoad\Module\Common\FileSystem\FS;
use Internal\DLoad\Module\Common\Libc;
use Internal\DLoad\Module\Common\OperatingSystem;
use Internal\DLoad\Module\Common\Stability;
use Internal\DLoad\Module\Config\Schema\Action\Download as DownloadConfig;
use Internal\DLoad\Module\Config\Schema\Downloader as DownloaderConfig;
use Internal\DLoad\Module\Config\Schema\Embed\Software;
use Internal\DLoad\Module\Downloader\Downloader;
use Internal\DLoad\Module\Downloader\Internal\AssetSelection\AssetSelector;
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

    /**
     * Assets of the Mago 1.51.2 release: Rust target triples with glibc and musl builds.
     */
    private const MAGO_ASSETS = [
        'mago-1.51.2-aarch64-apple-darwin.tar.gz',
        'mago-1.51.2-aarch64-unknown-linux-gnu.tar.gz',
        'mago-1.51.2-aarch64-unknown-linux-musl.tar.gz',
        'mago-1.51.2-arm-unknown-linux-gnueabi.tar.gz',
        'mago-1.51.2-arm-unknown-linux-gnueabihf.tar.gz',
        'mago-1.51.2-arm-unknown-linux-musleabi.tar.gz',
        'mago-1.51.2-arm-unknown-linux-musleabihf.tar.gz',
        'mago-1.51.2-armv7-unknown-linux-gnueabihf.tar.gz',
        'mago-1.51.2-armv7-unknown-linux-musleabihf.tar.gz',
        'mago-1.51.2-wasm.tar.gz',
        'mago-1.51.2-x86_64-apple-darwin.tar.gz',
        'mago-1.51.2-x86_64-pc-windows-gnu.tar.gz',
        'mago-1.51.2-x86_64-pc-windows-msvc.zip',
        'mago-1.51.2-x86_64-unknown-freebsd.tar.gz',
        'mago-1.51.2-x86_64-unknown-linux-gnu.tar.gz',
        'mago-1.51.2-x86_64-unknown-linux-musl.tar.gz',
        'source-code.tar.gz',
        'source-code.zip',
    ];

    /**
     * Assets of the TigerBeetle 0.17.9 release: every build has a `-debug` twin listed first.
     */
    private const TIGERBEETLE_ASSETS = [
        'tigerbeetle-aarch64-linux-debug.zip',
        'tigerbeetle-aarch64-linux.zip',
        'tigerbeetle-universal-macos-debug.zip',
        'tigerbeetle-universal-macos.zip',
        'tigerbeetle-x86_64-linux-debug.zip',
        'tigerbeetle-x86_64-linux.zip',
        'tigerbeetle-x86_64-windows-debug.zip',
        'tigerbeetle-x86_64-windows.zip',
        'vortex-driver-zig-aarch64-linux.zip',
        'vortex-driver-zig-x86_64-linux.zip',
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

    public static function provideBunMuslHosts(): \Generator
    {
        yield 'Linux x64' => [Architecture::X86_64, 'bun-linux-x64-musl.zip'];
        yield 'Linux arm64' => [Architecture::ARM_64, 'bun-linux-aarch64-musl.zip'];
    }

    public static function provideTigerBeetleHosts(): \Generator
    {
        yield 'Linux x64' => [OperatingSystem::Linux, Architecture::X86_64, 'tigerbeetle-x86_64-linux.zip'];
        yield 'Linux arm64' => [OperatingSystem::Linux, Architecture::ARM_64, 'tigerbeetle-aarch64-linux.zip'];
        yield 'Windows x64' => [OperatingSystem::Windows, Architecture::X86_64, 'tigerbeetle-x86_64-windows.zip'];
    }

    public static function provideMagoHosts(): \Generator
    {
        yield 'Linux x64 glibc' => [Architecture::X86_64, Libc::Gnu, 'mago-1.51.2-x86_64-unknown-linux-gnu.tar.gz'];
        yield 'Linux x64 musl' => [Architecture::X86_64, Libc::Musl, 'mago-1.51.2-x86_64-unknown-linux-musl.tar.gz'];
        yield 'Linux arm64 glibc' => [Architecture::ARM_64, Libc::Gnu, 'mago-1.51.2-aarch64-unknown-linux-gnu.tar.gz'];
        yield 'Linux arm64 musl' => [Architecture::ARM_64, Libc::Musl, 'mago-1.51.2-aarch64-unknown-linux-musl.tar.gz'];
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

    #[DataProvider('provideBunMuslHosts')]
    #[Test]
    public function bunMuslBuildIsSelectedOnAMuslHost(Architecture $arch, string $expected): void
    {
        $result = $this->download(
            self::registryEntry('bun'),
            'oven-sh/bun',
            'bun-v1.4.2',
            self::BUN_ASSETS,
            OperatingSystem::Linux,
            $arch,
            Libc::Musl,
        );

        Assert::same($result->file->getFilename(), $expected);
    }

    #[DataProvider('provideTigerBeetleHosts')]
    #[Test]
    public function tigerBeetleReleaseBuildIsSelectedOverTheDebugOne(
        OperatingSystem $os,
        Architecture $arch,
        string $expected,
    ): void {
        $result = $this->download(
            self::registryEntry('tigerbeetle'),
            'tigerbeetle/tigerbeetle',
            '0.17.9',
            self::TIGERBEETLE_ASSETS,
            $os,
            $arch,
            Libc::Gnu,
        );

        Assert::same($result->file->getFilename(), $expected);
    }

    #[DataProvider('provideMagoHosts')]
    #[Test]
    public function magoBuildForTheHostLibcIsSelected(Architecture $arch, Libc $libc, string $expected): void
    {
        $result = $this->download(
            self::registryEntry('mago'),
            'carthage-software/mago',
            '1.51.2',
            self::MAGO_ASSETS,
            OperatingSystem::Linux,
            $arch,
            $libc,
        );

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
        return $this->download($software, 'oven-sh/bun', 'bun-v1.4.2', self::BUN_ASSETS, $os, $arch, Libc::Gnu);
    }

    /**
     * Runs the downloader against one release with the given assets.
     *
     * @param non-empty-string $repositoryName
     * @param non-empty-string $tag
     * @param list<non-empty-string> $assets Asset names in the order the API lists them.
     */
    private function download(
        Software $software,
        string $repositoryName,
        string $tag,
        array $assets,
        OperatingSystem $os,
        Architecture $arch,
        Libc $libc,
    ): DownloadResult {
        $repository = new RepositoryStub($repositoryName);
        $release = new ReleaseStub($repository, $tag, Version::fromVersionString(\preg_replace('/^[a-z]+-/', '', $tag)), tag: $tag);
        $release->setAssets(\array_map(
            static fn(string $name): AssetStub => new AssetStub(
                $release,
                $name,
                "https://github.com/{$repositoryName}/releases/download/{$tag}/{$name}",
                OperatingSystem::tryFromBuildName($name),
                Architecture::tryFromBuildName($name),
            ),
            $assets,
        ));
        $repository = new RepositoryStub($repositoryName, ReleasesCollection::create([$release]));

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
            assetSelector: new AssetSelector($os, $arch, $libc, new ArchiveFactory()),
        );
        $task = $downloader->download($software, DownloadConfig::fromSoftwareId($software->getId()), static fn(): null => null);

        /** @var DownloadResult */
        return await(($task->handler)());
    }
}
