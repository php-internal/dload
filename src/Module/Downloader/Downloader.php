<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\Downloader;

use Internal\DLoad\Module\Common\Architecture;
use Internal\DLoad\Module\Common\Config\DownloaderConfig;
use Internal\DLoad\Module\Common\Config\Embed\Software;
use Internal\DLoad\Module\Common\OperatingSystem;
use Internal\DLoad\Module\Common\Stability;
use Internal\DLoad\Module\Downloader\Internal\DownloadContext;
use Internal\DLoad\Module\Downloader\Task\DownloadTask;
use Internal\DLoad\Module\Repository\AssetInterface;
use Internal\DLoad\Module\Repository\ReleaseInterface;
use Internal\DLoad\Module\Repository\RepositoryInterface;
use Internal\DLoad\Module\Repository\RepositoryProvider;
use Internal\DLoad\Service\Destroyable;
use Internal\DLoad\Service\Logger;
use React\Promise\PromiseInterface;

use function React\Async\await;
use function React\Async\coroutine;

final class Downloader
{
    public function __construct(
        private readonly DownloaderConfig $config,
        private readonly Logger $logger,
        private readonly RepositoryProvider $repositoryProvider,
        private readonly Architecture $architecture,
        private readonly OperatingSystem $operatingSystem,
        private readonly Stability $stability,
    ) {}

    /**
     * Create task to download software.
     *
     * @param \Closure(Progress): mixed $onProgress Callback to report progress.
     *        Exception thrown in this callback will stop and revert the task.
     */
    public function download(
        Software $software,
        \Closure $onProgress,
    ): DownloadTask {
        $context = new DownloadContext(
            software: $software,
            onProgress: $onProgress,
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
                } catch (\Throwable $e) {
                    $this->logger->exception($e);
                    yield;
                    goto start;
                } finally {
                    $repository instanceof Destroyable and $repository->destroy();
                }

                return $context->file;
            });
        };

        return new DownloadTask(
            software: $software,
            onProgress: $onProgress,
            handler: $handler,
        );
    }

    /**
     * @return \Closure(): ReleaseInterface
     */
    private function processRepository(RepositoryInterface $repository, DownloadContext $context): \Closure
    {
        return function () use ($repository, $context): ReleaseInterface {
            /** @var ReleaseInterface[] $releases */
            $releases = $repository->getReleases()
                ->minimumStability($this->stability)
                ->sortByVersion()->toArray();

            $this->logger->debug('%d releases found.', \count($releases));

            process_release:
            $releases === [] and throw new \RuntimeException('No relevant release found.');
            $release = \array_shift($releases);

            $this->logger->debug('Trying to load release `%s`', $release->getName());

            try {
                await(coroutine($this->processRelease($release, $context)));
                return $release;
            } catch (\Throwable $e) {
                $this->logger->exception($e);
                goto process_release;
            }
        };
    }

    /**
     * @return \Closure(): AssetInterface
     */
    private function processRelease(ReleaseInterface $asset, DownloadContext $context): \Closure
    {
        return function () use ($asset, $context): AssetInterface {
            /** @var AssetInterface[] $assets */
            $assets = $asset->getAssets()
                ->whereArchitecture($this->architecture)
                ->whereOperatingSystem($this->operatingSystem)
                ->whereNameMatches($context->repoConfig->assetPattern)
                ->toArray();

            $this->logger->debug('%d assets found.', \count($assets));

            process_asset:
            $assets === [] and throw new \RuntimeException('No relevant asset found.');
            $asset = \array_shift($assets);
            $this->logger->debug('Trying to load asset `%s`', $asset->getName());
            try {
                await(coroutine($this->processAsset($asset, $context)));
                return $asset;
            } catch (\Throwable $e) {
                $this->logger->exception($e);
                goto process_asset;
            }
        };
    }

    /**
     * @return \Closure(): \SplFileObject
     */
    private function processAsset(AssetInterface $asset, DownloadContext $context): \Closure
    {
        return function () use ($asset, $context): \SplFileObject {
            // Create a file
            $temp = $this->getTempDirectory() . DIRECTORY_SEPARATOR . $asset->getName();
            $file = new \SplFileObject($temp, 'wb+');

            $this->logger->debug('Downloading into ' . $temp);

            await(coroutine(
                (static function () use ($asset, $context, $file): void {
                    $generator = $asset->download(
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
