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
use Internal\DLoad\Module\Downloader\Exception\ReleaseGone;
use Internal\DLoad\Module\Downloader\Internal\Diagnostics\DownloadDiagnostics;
use Internal\DLoad\Module\Downloader\Internal\AssetSelection\AssetSelector;
use Internal\DLoad\Module\Downloader\Internal\AssetSelection\Rule\ArchitectureRule;
use Internal\DLoad\Module\Downloader\Internal\DownloadContext;
use Internal\DLoad\Module\Downloader\Task\DownloadResult;
use Internal\DLoad\Module\Downloader\Task\DownloadTask;
use Internal\DLoad\Module\Registry\VersionRegistry;
use Internal\DLoad\Module\Repository\AssetInterface;
use Internal\DLoad\Module\Repository\Collection\ReleasesCollection;
use Internal\DLoad\Module\Repository\Exception\AssetNotFoundException;
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
        private readonly VersionRegistry $registry,
        private readonly AssetSelector $assetSelector,
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

                // The registry keeps track of which software is served from which repository. The
                // identity comes from the repository, not the config: the factory may have reduced
                // a full URL to the path the repository stores its releases under.
                $context->repositoryId = $repository->getId();
                $this->registry->attach($context->repositoryId, $context->software->getId());
                $context->repositoryAttempt = $context->diagnostics->addRepository(
                    type: $context->repoConfig->type,
                    name: $context->repositoryId->server === null
                        ? $repository->getName()
                        : $context->repositoryId->server . '/' . $repository->getName(),
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
    private function processRepository(Repository $repository, DownloadContext $context, bool $mayRetry = true): \Closure
    {
        return function () use ($repository, $context, $mayRetry): ReleaseInterface {
            // Set when a release turned out to be deleted: the release list is outdated then
            $forgotten = false;

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
            if ($releases === []) {
                // The list was outdated: ask the repository again once, with the deleted releases forgotten
                /** @var bool $forgotten */
                if ($forgotten && $mayRetry) {
                    return $this->retryRepository($context);
                }

                throw new NotFound('No relevant release found.');
            }

            $context->release = \array_shift($releases);
            $context->releaseAttempt = $context->repositoryAttempt->addRelease($context->release->getName());

            $this->logger->debug('Loading release `%s`', $context->release->getName());

            try {
                await(coroutine($this->processRelease($context)));
                return $context->release;
            } catch (ReleaseGone $e) {
                // The registry must not offer this release again, and the list needs a fresh check
                $this->registry->forget($context->repositoryId, $context->release->getTag());
                $forgotten = true;

                $context->releaseAttempt->reason ??= $e->getMessage();
                $this->logger->debug($e->getMessage());
                goto process_release;
            } catch (NotFound $e) {
                $context->releaseAttempt->reason ??= $e->getMessage();
                $this->logger->debug($e->getMessage());
                $this->logger->exception($e, important: false);
                goto process_release;
            }
        };
    }

    /**
     * Fetches the release list anew after deleted releases were forgotten and tries once more.
     *
     * @throws NotFound When the fresh list has nothing suitable either.
     */
    private function retryRepository(DownloadContext $context): ReleaseInterface
    {
        $this->logger->info('Release list of `%s` is outdated, fetching it again.', $context->repoConfig->uri);
        $repository = $this->repositoryProvider->getByConfig($context->repoConfig);

        try {
            return await(coroutine($this->processRepository($repository, $context, mayRetry: false)));
        } finally {
            $repository instanceof Destroyable and $repository->destroy();
        }
    }

    /**
     * Processes a release to find suitable assets.
     *
     * If software has binary configuration, only assets for the host OS and architecture are tried.
     * Otherwise assets for another platform are tried after them.
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

            // Phar assets usually don't depend on OS or architecture; without a binary configuration
            // there is nothing to verify the choice, so assets for another platform stay as a fallback.
            $strict = $context->actionConfig->type !== Type::Phar && $context->software->binary !== null;
            $selection = $this->assetSelector->select(
                $context->release->getAssets(),
                $context->repoConfig->assetPattern,
                $context->actionConfig->type,
                $strict,
            );
            $this->logger->debug('%d matching assets found.', \count($selection->candidates));

            $selection->isEmpty() and throw new NotFound(
                $strict
                    ? \sprintf(
                        'no asset matches OS `%s`, architecture `%s`, name pattern `%s`%s',
                        $this->operatingSystem->value,
                        $this->architecture->value,
                        $context->repoConfig->assetPattern,
                        $this->describeFormatFilter($context->actionConfig),
                    )
                    : \sprintf(
                        'no asset matches name pattern `%s`%s',
                        $context->repoConfig->assetPattern,
                        $this->describeFormatFilter($context->actionConfig),
                    ),
            );

            foreach ($selection->sorted() as $candidate) {
                $this->logger->debug(
                    'Asset `%s` ranked: %s.',
                    $candidate->asset->getName(),
                    \implode(' ', \array_map(
                        static fn(string $key, int $rank): string => "{$key}={$rank}",
                        \array_keys($candidate->ranks),
                        $candidate->ranks,
                    )),
                );
            }

            $asset = $this->tryProcessAssets($selection->assets(), $context);
            foreach ($selection->candidates as $candidate) {
                // The host runs this build only through emulation, which may be missing (Rosetta 2 is optional)
                $candidate->asset === $asset && ($candidate->ranks[ArchitectureRule::KEY] ?? null) === ArchitectureRule::EMULATED and $this->logger->warning(
                    'No `%s` build of `%s` found, `%s` needs an x86-64 emulator to run.',
                    $this->architecture->value,
                    $context->software->getId(),
                    $asset->getName(),
                );
            }

            return $asset;
        };
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
        // Stays true while every failed asset answered "not found": then the release itself is gone
        $gone = $assets !== [];

        process_asset:
        if ($assets === []) {
            /** @var bool $gone */
            $gone and throw new ReleaseGone('every matching asset of the release is no longer available');

            throw new NotFound('none of the matching assets could be downloaded');
        }

        $context->asset = \array_shift($assets);
        $this->logger->debug('Trying to load asset `%s`', $context->asset->getName());
        try {
            await(coroutine($this->processAsset($context)));
            return $context->asset;
        } catch (RateLimitException $e) {
            // Retrying other assets makes the situation worse: report the limit immediately
            throw $e;
        } catch (\Throwable $e) {
            $gone = $gone && $e instanceof AssetNotFoundException;
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
}
