<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\Downloader;

use Internal\DLoad\Module\Archive\ArchiveFactory;
use Internal\DLoad\Module\Common\Architecture;
use Internal\DLoad\Module\Common\Config\Action\Download as DownloadConfig;
use Internal\DLoad\Module\Common\Config\Downloader as DownloaderConfig;
use Internal\DLoad\Module\Common\Config\Embed\Software;
use Internal\DLoad\Module\Common\OperatingSystem;
use Internal\DLoad\Module\Common\Stability;
use Internal\DLoad\Module\Downloader\Internal\DownloadContext;
use Internal\DLoad\Module\Downloader\Task\DownloadResult;
use Internal\DLoad\Module\Downloader\Task\DownloadTask;
use Internal\DLoad\Module\Repository\AssetInterface;
use Internal\DLoad\Module\Repository\ReleaseInterface;
use Internal\DLoad\Module\Repository\Repository;
use Internal\DLoad\Module\Repository\RepositoryProvider;
use Internal\DLoad\Service\Destroyable;
use Internal\DLoad\Service\Logger;
use React\Promise\PromiseInterface;

use function React\Async\await;
use function React\Async\coroutine;

/**
 * Core downloader service responsible for fetching software assets.
 *
 * Manages the entire download process from repository selection to asset downloading.
 * Supports multiple repositories and provides fallback capability when a repository fails.
 *
 * ```php
 *  // Create a download task
 *  $task = $downloader->download($software, $config, function(Progress $progress) {
 *      echo sprintf("Downloaded: %d/%d bytes\n", $progress->current, $progress->total);
 *  });
 *
 *  // Execute the task
 *  $result = await($task->handler());
 * ```
 */
final class Downloader
{
    public function __construct(
        private readonly DownloaderConfig $config,
        private readonly Logger $logger,
        private readonly RepositoryProvider $repositoryProvider,
        private readonly Architecture $architecture,
        private readonly OperatingSystem $operatingSystem,
        private readonly Stability $stability,
        private readonly ArchiveFactory $archiveService,
    ) {}

    /**
     * Creates a task to download software.
     *
     * Prepares a download task that can be executed to obtain the software asset. The task tries repositories
     * sequentially until one succeeds.
     *
     * @param Software $software Software package configuration
     * @param DownloadConfig $actionConfig Download action configuration
     * @param \Closure(Progress): mixed $onProgress Callback to report download progress.
     *        Exception thrown in this callback will stop and revert the task.
     * @return DownloadTask Executable download task object
     */
    public function download(
        Software $software,
        DownloadConfig $actionConfig,
        \Closure $onProgress,
    ): DownloadTask {
        $context = new DownloadContext(
            software: $software,
            onProgress: $onProgress,
            actionConfig: $actionConfig,
        );

        $repositories = $software->repositories;
        $handler = function () use ($repositories, $context): PromiseInterface {
            return coroutine(function () use ($repositories, $context) {
                // Try every repo to load software.
                start:
                $repositories === [] and throw new \RuntimeException('No relevant repository found.');
                $context->repoConfig = \array_shift($repositories);
                $repository = $this->repositoryProvider->getByConfig($context->repoConfig);

                $this->logger->debug('Trying to load from repo `%s`', $repository->getName());

                try {
                    await(coroutine($this->processRepository($repository, $context)));

                    return new DownloadResult(
                        file: $context->file,
                        version: $context->release->getVersion(),
                    );
                } catch (\Throwable $e) {
                    $this->logger->exception($e);
                    goto start;
                } finally {
                    $repository instanceof Destroyable and $repository->destroy();
                }
            });
        };

        return new DownloadTask(
            software: $software,
            onProgress: $onProgress,
            handler: $handler,
        );
    }

    /**
     * Processes the repository to find suitable releases.
     *
     * Fetches and filters releases from the repository based on stability and version constraints.
     *
     * @param Repository $repository Repository to process
     * @param DownloadContext $context Download context information
     * @return \Closure(): ReleaseInterface Closure that returns the selected release
     */
    private function processRepository(Repository $repository, DownloadContext $context): \Closure
    {
        return function () use ($repository, $context): ReleaseInterface {
            $this->logger->info(
                'Loading releases from `%s` repository %s',
                $context->repoConfig->type,
                $repository->getName(),
            );

            $releasesCollection = $repository->getReleases()
                ->minimumStability($this->stability);

            // Filter by version if specified
            $context->actionConfig->version === null or $releasesCollection = $releasesCollection
                ->satisfies($context->actionConfig->version);

            /** @var ReleaseInterface[] $releases */
            $releases = $releasesCollection->limit(10)->sortByVersion()->toArray();

            $this->logger->debug('%d releases found.', \count($releases));

            process_release:
            // Try without limit
            $releases === [] and $releases = $releasesCollection->limit(0)->toArray();
            $releases === [] and throw new \RuntimeException('No relevant release found.');
            $context->release = \array_shift($releases);

            $this->logger->info('Loading release `%s`', $context->release->getName());

            try {
                await(coroutine($this->processRelease($context)));
                return $context->release;
            } catch (\Throwable $e) {
                $this->logger->error('%s', $e->getMessage());
                $this->logger->exception($e);
                goto process_release;
            }
        };
    }

    /**
     * Processes a release to find suitable assets.
     *
     * Filters assets from the release based on architecture, operating system, and name pattern.
     *
     * @param DownloadContext $context Download context information
     * @return \Closure(): AssetInterface Closure that returns the selected asset
     */
    private function processRelease(DownloadContext $context): \Closure
    {
        return function () use ($context): AssetInterface {
            /** @var AssetInterface[] $assets */
            $assets = $context->release->getAssets()
                ->whereArchitecture($this->architecture)
                ->whereOperatingSystem($this->operatingSystem)
                ->whereNameMatches($context->repoConfig->assetPattern)
                ->whereFileExtensions($this->archiveService->getSupportedExtensions())
                ->toArray();

            $this->logger->debug('%d assets found.', \count($assets));

            process_asset:
            $assets === [] and throw new \RuntimeException('No relevant asset found.');
            $context->asset = \array_shift($assets);
            $this->logger->debug('Trying to load asset `%s`', $context->asset->getName());
            try {
                await(coroutine($this->processAsset($context)));
                return $context->asset;
            } catch (\Throwable $e) {
                $this->logger->exception($e);
                goto process_asset;
            }
        };
    }

    /**
     * Downloads the selected asset to a temporary file.
     *
     * Creates a temporary file and downloads the asset content, reporting progress via callback.
     *
     * @param DownloadContext $context Download context information
     * @return \Closure(): \SplFileObject Closure that returns the downloaded file
     */
    private function processAsset(DownloadContext $context): \Closure
    {
        return function () use ($context): \SplFileObject {
            // Create a file
            $temp = $this->getTempDirectory() . DIRECTORY_SEPARATOR . $context->asset->getName();
            $file = new \SplFileObject($temp, 'wb+');

            $this->logger->info('Downloading into %s', $temp);

            await(coroutine(
                (static function () use ($context, $file): void {
                    $generator = $context->asset->download(
                        static fn(int $dlNow, int $dlSize, array $info) => ($context->onProgress)(
                            new Progress(
                                total: $dlSize,
                                current: $dlNow,
                                message: 'downloading...',
                            ),
                        ),
                    );

                    foreach ($generator as $chunk) {
                        $file->fwrite($chunk);
                    }
                }),
            )->then(null, static function (\Throwable $e) use ($file): void {
                @\unlink($file->getPath());
                throw $e;
            }));

            return $context->file = $file;
        };
    }

    /**
     * Returns the temporary directory path for file downloads.
     *
     * Uses the configured directory if available and writable, otherwise defaults to system temp directory.
     *
     * @return non-empty-string Path to temporary directory
     * @throws \LogicException When configured directory is not writable
     */
    private function getTempDirectory(): string
    {
        $temp = $this->config->tmpDir;
        if ($temp !== null) {
            (\is_dir($temp) && \is_writable($temp)) or throw new \LogicException(
                \sprintf('Directory "%s" is not writeable.', $temp),
            );

            return $temp;
        }

        return \sys_get_temp_dir();
    }
}
