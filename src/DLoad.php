<?php

declare(strict_types=1);

namespace Internal\DLoad;

use Internal\DLoad\Module\Archive\ArchiveFactory;
use Internal\DLoad\Module\Binary\BinaryProvider;
use Internal\DLoad\Module\Common\Config\Action\Download as DownloadConfig;
use Internal\DLoad\Module\Common\Config\Embed\File;
use Internal\DLoad\Module\Common\Config\Embed\Software;
use Internal\DLoad\Module\Common\FileSystem\Path;
use Internal\DLoad\Module\Common\Input\Destination;
use Internal\DLoad\Module\Common\OperatingSystem;
use Internal\DLoad\Module\Downloader\Downloader;
use Internal\DLoad\Module\Downloader\SoftwareCollection;
use Internal\DLoad\Module\Downloader\Task\DownloadResult;
use Internal\DLoad\Module\Downloader\Task\DownloadTask;
use Internal\DLoad\Module\Downloader\TaskManager;
use Internal\DLoad\Module\Version\Constraint;
use Internal\DLoad\Module\Version\Version;
use Internal\DLoad\Service\Logger;
use React\Promise\PromiseInterface;
use Symfony\Component\Console\Output\OutputInterface;

use function React\Promise\resolve;

/**
 * Main application facade providing simplified access to download functionality.
 *
 * Acts as a high-level interface for downloading and extracting software packages
 * based on configuration actions.
 *
 * ```php
 * $dload = $container->get(DLoad::class);
 * $dload->addTask(new DownloadConfig('rr', '^2.12.0'));
 * $dload->run();
 * ```
 *
 * @internal
 */
final class DLoad
{
    /** @var bool Flag to use mock data instead of actual downloads for testing */
    public bool $useMock = false;

    public function __construct(
        private readonly Logger $logger,
        private readonly TaskManager $taskManager,
        private readonly SoftwareCollection $softwareCollection,
        private readonly Downloader $downloader,
        private readonly ArchiveFactory $archiveFactory,
        private readonly Destination $configDestination,
        private readonly OutputInterface $output,
        private readonly BinaryProvider $binaryProvider,
        private readonly OperatingSystem $os,
    ) {}

    /**
     * Adds a download task to the execution queue.
     *
     * Creates and schedules a task to download and extract a software package based on the provided action.
     * Skips task creation if binary already exists with a satisfying version and force flag is not set.
     *
     * @param DownloadConfig $action Download configuration action
     * @param bool $force Whether to force download even if binary exists
     * @throws \RuntimeException When software package is not found
     */
    public function addTask(DownloadConfig $action, bool $force = false): void
    {
        // Find Software
        $software = $this->softwareCollection->findSoftware($action->software) ?? throw new \RuntimeException(
            "Software `{$action->software}` not found in registry.",
        );

        // Check if binary already exists and satisfies version constraint
        $destinationPath = $this->getDestinationPath($action);

        if (!$force && $software->binary !== null) {
            // Check different constraints
            $binary = $this->binaryProvider->getBinary($destinationPath, $software->binary);

            if ($binary === null) {
                goto add_task;
            }

            \assert($binary !== null);
            $version = $binary->getVersionString();
            if ($action->version === null) {
                $this->logger->info(
                    'Binary `%s` exists with version `%s`, but no version constraint specified. Skipping download.',
                    $binary->getName(),
                    $version ?? 'unknown',
                );
                $this->logger->info('Use flag `--force` to force download.');

                // Skip task creation entirely
                return;
            }

            // Create VersionConstraint DTO for enhanced constraint checking
            $versionConstraint = Constraint::fromConstraintString($action->version);

            // Check if binary exists and satisfies enhanced version constraint
            $binaryVersion = $binary->getVersion();
            if ($binaryVersion !== null && $versionConstraint->isSatisfiedBy($binaryVersion)) {
                $this->logger->info(
                    'Binary `%s` exists with version `%s`, satisfies constraint `%s`. Skipping download.',
                    $binary->getName(),
                    $binaryVersion->string,
                    (string) $versionConstraint,
                );
                $this->logger->info('Use flag `--force` to force download.');

                // Skip task creation entirely
                return;
            }

            // Download a newer version only if the version is specified
            if ($version !== null) {
                // todo
            }
        }

        add_task:

        $this->taskManager->addTask(function () use ($software, $action): void {
            // Create a Download task
            $task = $this->prepareDownloadTask($software, $action);

            // Extract files
            ($task->handler)()->then($this->prepareExtractTask($software, $action));
        });
    }

    /**
     * Executes all queued download tasks.
     *
     * Processes all scheduled tasks sequentially until completion.
     */
    public function run(): void
    {
        $this->taskManager->await();
    }

    /**
     * Creates a download task for the specified software package.
     *
     * Either uses a mock task (for testing) or creates a real download task.
     *
     * @param Software $software Software package configuration
     * @param DownloadConfig $action Download action configuration
     * @return DownloadTask Task object for downloading the specified software
     */
    private function prepareDownloadTask(Software $software, DownloadConfig $action): DownloadTask
    {
        return $this->useMock
            ? new DownloadTask(
                $software,
                static fn() => null,
                static fn(): PromiseInterface => resolve(
                    new DownloadResult(
                        new \SplFileInfo(Info::ROOT_DIR . '/resources/mock/roadrunner-2024.1.5-windows-amd64.zip'),
                        Version::fromVersionString('2024.1.5'),
                    ),
                ),
            )
            : $this->downloader->download($software, $action, static fn() => null);
    }

    /**
     * Creates a closure to handle extraction of downloaded files.
     *
     * @param Software $software Software package configuration
     * @param DownloadConfig $action Download action configuration
     * @return \Closure(DownloadResult): void Function that extracts files from the downloaded archive
     */
    private function prepareExtractTask(Software $software, DownloadConfig $action): \Closure
    {
        return function (DownloadResult $downloadResult) use ($software, $action): void {
            $fileInfo = $downloadResult->file;

            // Create a copy of the files list with binary included if necessary
            $files = $this->filesToExtract($software);

            // Create destination directory if it doesn't exist
            $path = $this->getDestinationPath($action);
            if (!\is_dir((string) $path)) {
                $this->logger->info('Creating directory %s', (string) $path);
                @\mkdir((string) $path, 0755, true);
            }

            // If no extraction rules are defined, do not extract anything
            // and just copy the file to the destination
            if ($files === []) {
                $this->logger->debug(
                    'No files to extract for `%s`, copying the downloaded file to the destination.',
                    $fileInfo->getFilename(),
                );

                \copy(
                    $fileInfo->getRealPath() ?: $fileInfo->getPathname(),
                    (string) $path->join($fileInfo->getFilename()),
                );
                return;
            }

            $archive = $this->archiveFactory->create($fileInfo);
            $extractor = $archive->extract();
            $this->logger->info('Extracting %s', $fileInfo->getFilename());

            while ($extractor->valid()) {
                $file = $extractor->current();
                \assert($file instanceof \SplFileInfo);

                $to = $this->shouldBeExtracted($file, $files, $action);
                $this->logger->debug(
                    $to === null ? 'Skipping %s%s' : 'Extracting %s to %s',
                    $file->getFilename(),
                    (string) $to?->getPathname(),
                );

                if ($to === null) {
                    $extractor->next();
                    continue;
                }

                $isOverwriting = $to->isFile();
                $extractor->send($to);

                // Success
                $path = $to->getRealPath() ?: $to->getPathname();
                $this->output->writeln(
                    \sprintf(
                        '%s (<comment>%s</comment>) has been %sinstalled into <info>%s</info>',
                        $to->getFilename(),
                        $downloadResult->version,
                        $isOverwriting ? 're' : '',
                        $path,
                    ),
                );

                $to->isExecutable() or @\chmod($path, 0755);
            }
        };
    }

    /**
     * Determines the target path for an extracted file based on file mapping configurations.
     *
     * @param \SplFileInfo $source Source file from the archive
     * @param list<File> $mapping File mapping configurations
     * @param DownloadConfig $action Download action configuration
     * @return \SplFileInfo|null Target file path or null if file should not be extracted
     */
    private function shouldBeExtracted(\SplFileInfo $source, array $mapping, DownloadConfig $action): ?\SplFileInfo
    {
        $path = $this->getDestinationPath($action);

        foreach ($mapping as $conf) {
            if (\preg_match($conf->pattern, $source->getFilename())) {
                $newName = match (true) {
                    $conf->rename === null => $source->getFilename(),
                    $source->getExtension() === '' => $conf->rename,
                    default => $conf->rename . '.' . $source->getExtension(),
                };

                return new \SplFileInfo((string) $path->join($newName));
            }
        }

        return null;
    }

    /**
     * Gets the destination path for file extraction, prioritizing global destination path over custom extraction path.
     *
     * @param DownloadConfig $action Download action configuration
     */
    private function getDestinationPath(DownloadConfig $action): Path
    {
        return Path::create($this->configDestination->path ?? $action->extractPath ?? (string) \getcwd());
    }

    /**
     * @return list<File>
     */
    private function filesToExtract(Software $software): array
    {
        $files = $software->files;
        if ($software->binary !== null) {
            $binary = new File();
            $binary->pattern = $software->binary->pattern
                ?? "/^{$software->binary->name}{$this->os->getBinaryExtension()}$/";
            $binary->rename = $software->binary->name;
            $files[] = $binary;
        }

        return $files;
    }
}
