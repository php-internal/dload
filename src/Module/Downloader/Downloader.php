<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\Downloader;

use Internal\Destroy\Destroyable;
use Internal\DLoad\Module\Archive\ArchiveFactory;
use Internal\DLoad\Module\Common\Architecture;
use Internal\DLoad\Module\Common\FileSystem\FS;
use Internal\DLoad\Module\Common\OperatingSystem;
use Internal\DLoad\Module\Common\Stability;
use Internal\DLoad\Module\Config\Schema\Action\Download as DownloadConfig;
use Internal\DLoad\Module\Config\Schema\Action\Type;
use Internal\DLoad\Module\Config\Schema\Downloader as DownloaderConfig;
use Internal\DLoad\Module\Config\Schema\Embed\Software;
use Internal\DLoad\Module\Downloader\Exception\DownloadFailed;
use Internal\DLoad\Module\Downloader\Exception\NotFound;
use Internal\DLoad\Module\Downloader\Internal\Diagnostics\DownloadDiagnostics;
use Internal\DLoad\Module\Downloader\Internal\DownloadContext;
use Internal\DLoad\Module\Downloader\Task\DownloadResult;
use Internal\DLoad\Module\Downloader\Task\DownloadTask;
use Internal\DLoad\Module\Repository\AssetInterface;
use Internal\DLoad\Module\Repository\Collection\AssetsCollection;
use Internal\DLoad\Module\Repository\Collection\ReleasesCollection;
use Internal\DLoad\Module\Repository\Exception\RateLimitException;
use Internal\DLoad\Module\Repository\Exception\RepositoryException;
use Internal\DLoad\Module\Repository\ReleaseInterface;
use Internal\DLoad\Module\Repository\Repository;
use Internal\DLoad\Module\Repository\RepositoryProvider;
use Internal\DLoad\Module\Task\Progress;
use Internal\DLoad\Module\Version\Constraint;
use Internal\DLoad\Service\Logger;
use Internal\Path;
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
    /** Number of release names collected for the failure report. */
    private const FETCHED_RELEASES_LIMIT = 10;

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
            tempDir: $this->getTempDirectory(),
            diagnostics: new DownloadDiagnostics(
                software: $software,
                actionConfig: $actionConfig,
                operatingSystem: $this->operatingSystem,
                architecture: $this->architecture,
                stability: $this->stability,
            ),
        );

        $repositories = $software->repositories;
        $handler = function () use ($repositories, $context): PromiseInterface {
            return coroutine(function () use ($repositories, $context) {
                // Try every repo to load software.
                start:
                $repositories === [] and throw new DownloadFailed(
                    software: $context->software->getId(),
                    report: $context->diagnostics->render(),
                );
                $context->repoConfig = \array_shift($repositories);
                $repository = $this->repositoryProvider->getByConfig($context->repoConfig);
                $context->repositoryAttempt = $context->diagnostics->addRepository(
                    type: $context->repoConfig->type,
                    name: $repository->getName(),
                    assetPattern: $context->repoConfig->assetPattern,
                );

                $this->logger->debug('Trying to load from repo `%s`', $repository->getName());

                try {
                    await(coroutine($this->processRepository($repository, $context)));

                    return new DownloadResult(
                        file: $context->file,
                        version: $context->release->getVersion(),
                    );
                } catch (NotFound $e) {
                    // Nothing suitable in this repository: the reason is already in the diagnostics
                    $this->logger->debug($e->getMessage());
                    goto start;
                } catch (RepositoryException $e) {
                    // The repository is unusable (API error, invalid token, rate limit, etc.):
                    // remember the reason and fall back to the next repository.
                    $context->repositoryAttempt->error = $e;
                    $this->logger->debug($e->getMessage());
                    $this->logger->exception($e, important: false);
                    goto start;
                } catch (\Throwable $e) {
                    $this->logger->exception($e, important: false);
                    throw $e;
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

            $allReleases = $repository->getReleases();
            if ($context->actionConfig->version !== null) {
                $constraint = Constraint::fromConstraintString($context->actionConfig->version);
                // Filter by version if specified
                $releasesCollection = $allReleases
                    ->minimumStability($constraint->minimumStability)
                    ->satisfies($constraint);
            } else {
                $releasesCollection = $allReleases
                    ->minimumStability($this->stability);
            }

            /** @var ReleaseInterface[] $releases */
            $releases = $releasesCollection->limit(10)->sortByVersion()->toArray();

            $this->logger->debug('%d releases found.', \count($releases));

            // Try without limit
            $releases === [] and $releases = $releasesCollection->limit(0)->toArray();

            $context->repositoryAttempt->matchedReleases = \count($releases);

            if ($releases === []) {
                // Show what the repository actually offers: it explains version and stability mismatches
                $context->repositoryAttempt->registerFetchedReleases($this->fetchReleaseNames($allReleases));

                throw new NotFound('No relevant release found.');
            }

            process_release:
            $releases === [] and throw new NotFound('No relevant release found.');
            $context->release = \array_shift($releases);
            $context->releaseAttempt = $context->repositoryAttempt->addRelease($context->release->getName());

            $this->logger->debug('Loading release `%s`', $context->release->getName());

            try {
                await(coroutine($this->processRelease($context)));
                return $context->release;
            } catch (NotFound $e) {
                $context->releaseAttempt->reason ??= $e->getMessage();
                $this->logger->debug($e->getMessage());
                $this->logger->exception($e, important: false);
                goto process_release;
            }
        };
    }

    /**
     * Processes a release to find suitable assets.
     *
     * If software has binary configuration, filters assets using all criteria at once.
     * If no binary configuration exists, applies filters gradually to find the best matching asset.
     *
     * @param DownloadContext $context Download context information
     * @return \Closure(): AssetInterface Closure that returns the selected asset
     */
    private function processRelease(DownloadContext $context): \Closure
    {
        return function () use ($context): AssetInterface {
            // Remember all the release assets: it makes a "nothing matched" report meaningful
            $names = [];
            foreach ($context->release->getAssets() as $asset) {
                $names[] = $asset->getName();
            }

            $context->releaseAttempt->registerAssets($names);

            return match (true) {
                // Phar assets usually don't depend on OS or architecture, so we can use gradual filtering
                $context->actionConfig->type === Type::Phar => $this->findAssetWithGradualFiltering($context),
                // Use strict filtering when binary configuration exists
                $context->software->binary !== null => $this->findAssetWithStrictFiltering($context),
                // Use gradual filtering when no binary configuration exists
                default => $this->findAssetWithGradualFiltering($context),
            };
        };
    }

    /**
     * Finds an asset using strict filtering with all criteria applied at once.
     *
     * @param DownloadContext $context Download context information
     * @return AssetInterface Selected asset
     * @throws NotFound If no suitable asset is found
     */
    private function findAssetWithStrictFiltering(DownloadContext $context): AssetInterface
    {
        // Apply all filters at once: OS, architecture, and name pattern
        $assetsCollection = $context->release->getAssets()
            ->whereOperatingSystem($this->operatingSystem)
            ->whereArchitecture($this->architecture)
            ->whereNameMatches($context->repoConfig->assetPattern);

        /** @var AssetInterface[] $allAssets */
        $allAssets = $this->addFormatFilter($assetsCollection, $context->actionConfig)->toArray();
        $this->logger->debug('%d matching assets found.', \count($allAssets));

        $allAssets === [] and throw new NotFound(
            \sprintf(
                'no asset matches OS `%s`, architecture `%s`, name pattern `%s`%s',
                $this->operatingSystem->value,
                $this->architecture->value,
                $context->repoConfig->assetPattern,
                $this->describeFormatFilter($context->actionConfig),
            ),
        );

        // Sort assets by priority and try to process them
        $sortedAssets = $this->sortAssetsByPriority($allAssets, $this->archiveService->getSupportedExtensions());

        return $this->tryProcessAssets($sortedAssets, $context);
    }

    /**
     * Finds an asset using gradual filtering, trying different combinations of criteria.
     *
     * @param DownloadContext $context Download context information
     * @return AssetInterface Selected asset
     * @throws NotFound If no suitable asset is found
     */
    private function findAssetWithGradualFiltering(DownloadContext $context): AssetInterface
    {
        $assetsCollection = $context->release->getAssets()
            ->whereNameMatches($context->repoConfig->assetPattern);

        $assetsCollection = $this->addFormatFilter($assetsCollection, $context->actionConfig);
        $supportedExtensions = $this->archiveService->getSupportedExtensions();

        // If we got here, no assets were found with any filter combination
        \count($assetsCollection) === 0 and throw new NotFound(
            \sprintf(
                'no asset matches name pattern `%s`%s',
                $context->repoConfig->assetPattern,
                $this->describeFormatFilter($context->actionConfig),
            ),
        );

        // Try #1: Filter by both OS and architecture (most specific)
        $filteredAssets = $assetsCollection
            ->whereOperatingSystem($this->operatingSystem)
            ->whereArchitecture($this->architecture)
            ->toArray();

        if ($filteredAssets !== []) {
            $this->logger->debug(
                'Found %d assets matching OS %s and architecture %s.',
                \count($filteredAssets),
                $this->operatingSystem->value,
                $this->architecture->value,
            );
            $sortedAssets = $this->sortAssetsByPriority($filteredAssets, $supportedExtensions);
            try {
                return $this->tryProcessAssets($sortedAssets, $context);
            } catch (NotFound $e) {
                $this->logger->debug('Failed to process assets with OS and architecture filtering: %s', $e->getMessage());
                // Continue to next filter strategy
            }
        }

        // Try #2: Filter by OS only
        $filteredAssets = $assetsCollection
            ->whereOperatingSystem($this->operatingSystem)
            ->toArray();

        if ($filteredAssets !== []) {
            $this->logger->debug(
                'Found %d assets matching OS %s (any architecture).',
                \count($filteredAssets),
                $this->operatingSystem->value,
            );
            $sortedAssets = $this->sortAssetsByPriority($filteredAssets, $supportedExtensions);
            try {
                return $this->tryProcessAssets($sortedAssets, $context);
            } catch (NotFound $e) {
                $this->logger->debug('Failed to process assets with OS-only filtering: %s', $e->getMessage());
                // Continue to next filter strategy
            }
        }

        // Try #3: Filter by architecture only
        $filteredAssets = $assetsCollection
            ->whereArchitecture($this->architecture)
            ->toArray();

        if ($filteredAssets !== []) {
            $this->logger->debug(
                'Found %d assets matching architecture %s (any OS).',
                \count($filteredAssets),
                $this->architecture->value,
            );
            $sortedAssets = $this->sortAssetsByPriority($filteredAssets, $supportedExtensions);
            try {
                return $this->tryProcessAssets($sortedAssets, $context);
            } catch (NotFound $e) {
                $this->logger->debug('Failed to process assets with architecture-only filtering: %s', $e->getMessage());
                // Continue to next filter strategy
            }
        }

        // Try #4: Use name pattern only (least specific)
        $filteredAssets = $assetsCollection->toArray();

        $this->logger->debug(
            'Found %d assets matching name pattern (any OS, any architecture).',
            \count($filteredAssets),
        );
        $sortedAssets = $this->sortAssetsByPriority($filteredAssets, $supportedExtensions);
        return $this->tryProcessAssets($sortedAssets, $context);
    }

    /**
     * Tries to process assets from the provided list until one succeeds.
     *
     * @param AssetInterface[] $assets List of assets to try
     * @param DownloadContext $context Download context information
     * @return AssetInterface Successfully processed asset
     * @throws NotFound If no asset could be processed successfully
     */
    private function tryProcessAssets(array $assets, DownloadContext $context): AssetInterface
    {
        process_asset:
        $assets === [] and throw new NotFound('none of the matching assets could be downloaded');
        $context->asset = \array_shift($assets);
        $this->logger->debug('Trying to load asset `%s`', $context->asset->getName());
        try {
            await(coroutine($this->processAsset($context)));
            return $context->asset;
        } catch (RateLimitException $e) {
            // Retrying other assets makes the situation worse: report the limit immediately
            throw $e;
        } catch (\Throwable $e) {
            $context->releaseAttempt->addFailure($context->asset->getName(), $e);
            $this->logger->exception($e, important: false);
            goto process_asset;
        }
    }

    /**
     * Collects names of the first releases available in the repository for a failure report.
     *
     * @return list<string>
     */
    private function fetchReleaseNames(ReleasesCollection $releases): array
    {
        $names = [];
        foreach ($releases as $release) {
            $names[] = $release->getName();

            if (\count($names) >= self::FETCHED_RELEASES_LIMIT) {
                break;
            }
        }

        return $names;
    }

    /**
     * Describes the asset format restriction for failure reports.
     */
    private function describeFormatFilter(DownloadConfig $actionOptions): string
    {
        return match ($actionOptions->type) {
            Type::Phar => ' and the `phar` extension',
            Type::Archive => \sprintf(
                ' and one of the archive extensions: %s',
                \implode(', ', $this->archiveService->getSupportedExtensions()),
            ),
            default => '',
        };
    }

    /**
     * Sorts assets by priority with supported archives first, then other files.
     *
     * @param AssetInterface[] $assets List of assets to sort
     * @param list<non-empty-string> $supportedExtensions List of supported archive extensions
     * @return AssetInterface[] Sorted list of assets
     */
    private function sortAssetsByPriority(array $assets, array $supportedExtensions): array
    {
        $archiveAssets = [];
        $otherAssets = [];

        foreach ($assets as $asset) {
            $assetName = \strtolower($asset->getName());
            $isArchive = false;

            foreach ($supportedExtensions as $extension) {
                if (\str_ends_with($assetName, '.' . $extension)) {
                    $archiveAssets[] = $asset;
                    $isArchive = true;
                    break;
                }
            }

            $isArchive or $otherAssets[] = $asset;
        }

        return [...$archiveAssets, ...$otherAssets];
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
            $temp = $context->tempDir->join($context->asset->getName());
            $file = new \SplFileObject((string) $temp, 'wb+');

            $this->logger->info('Downloading into %s', (string) $temp);

            await(coroutine(
                (static function () use ($context, $file): void {
                    $generator = $context->asset->download(
                        // static fn(int $dlNow, int $dlSize, array $info): mixed => ($context->onProgress)(
                        //     new Progress(
                        //         total: $dlSize,
                        //         current: $dlNow,
                        //         message: 'downloading...',
                        //     ),
                        // ),
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
     * @throws \LogicException When configured directory is not writable
     */
    private function getTempDirectory(): Path
    {
        $temp = FS::tmpDir($this->config->tmpDir);

        $temp->exists() or \mkdir((string) $temp, recursive: true);
        $temp->isDir() && $temp->isWriteable() or throw new \LogicException(
            \sprintf('Directory "%s" is not writeable.', $temp),
        );

        return $temp;
    }

    /**
     * Adds format filter to the assets collection if specified in action options.
     *
     * @param AssetsCollection $collection Collection of assets to filter
     * @param DownloadConfig $actionOptions Download action options
     * @return AssetsCollection Filtered collection
     */
    private function addFormatFilter(AssetsCollection $collection, DownloadConfig $actionOptions): AssetsCollection
    {
        return match ($actionOptions->type) {
            Type::Phar => $collection->whereFileExtensions(['phar']),
            Type::Archive => $collection->whereFileExtensions($this->archiveService->getSupportedExtensions()),
            default => $collection,
        };
    }
}
