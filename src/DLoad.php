<?php

declare(strict_types=1);

namespace Internal\DLoad;

use Internal\DLoad\Module\Archive\ArchiveFactory;
use Internal\DLoad\Module\Common\Config\Action\Download as DownloadConfig;
use Internal\DLoad\Module\Common\Config\Embed\File;
use Internal\DLoad\Module\Common\Config\Embed\Software;
use Internal\DLoad\Module\Common\Input\Destination;
use Internal\DLoad\Module\Common\OperatingSystem;
use Internal\DLoad\Module\Downloader\Downloader;
use Internal\DLoad\Module\Downloader\Internal\BinaryExistenceChecker;
use Internal\DLoad\Module\Downloader\SoftwareCollection;
use Internal\DLoad\Module\Downloader\Task\DownloadResult;
use Internal\DLoad\Module\Downloader\Task\DownloadTask;
use Internal\DLoad\Module\Downloader\TaskManager;
use Internal\DLoad\Service\Logger;
use React\Promise\PromiseInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\StyleInterface;

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
        private readonly StyleInterface $io,
        private readonly BinaryExistenceChecker $binaryChecker,
        private readonly OperatingSystem $os,
    ) {}

    /**
     * Adds a download task to the execution queue.
     *
     * Creates and schedules a task to download and extract a software package based on the provided action.
     * Skips task creation if binary already exists and force flag is not set.
     *
     * @param DownloadConfig $action Download configuration action
     * @param bool $force Whether to force download even if binary exists
     * @throws \RuntimeException When software package is not found
     */
    public function addTask(DownloadConfig $action, bool $force = false): void
    {
        // Find Software
        $software = $this->softwareCollection->findSoftware($action->software) ?? throw new \RuntimeException(
            'Software not found.',
        );

        // Check if binary already exists
        $destinationPath = $this->configDestination->path ?? (string) \getcwd();
        if (!$force && $software->binary !== null && $this->binaryChecker->exists($destinationPath, $software->binary)) {
            $binaryPath = $this->binaryChecker->buildBinaryPath($destinationPath, $software->binary);
            $this->logger->info(
                "Binary {$binaryPath} already exists. Skipping download. Skipping download. Use --force to override.",
            );

            // Skip task creation entirely
            return;
        }

        $this->taskManager->addTask(function () use ($software, $action): void {
            // Create a Download task
            $task = $this->prepareDownloadTask($software, $action);

            // Extract files
            ($task->handler)()->then($this->prepareExtractTask($software));
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
                        '2024.1.5',
                    ),
                ),
            )
            : $this->downloader->download($software, $action, static fn() => null);
    }

    /**
     * Creates a closure to handle extraction of downloaded files.
     *
     * @param Software $software Software package configuration
     * @return \Closure(DownloadResult): void Function that extracts files from the downloaded archive
     */
    private function prepareExtractTask(Software $software): \Closure
    {
        return function (DownloadResult $downloadResult) use ($software): void {
            $fileInfo = $downloadResult->file;
            $archive = $this->archiveFactory->create($fileInfo);
            $extractor = $archive->extract();
            $this->logger->info('Extracting %s', $fileInfo->getFilename());

            // Create a copy of the files list with binary included if necessary
            $files = $this->filesToExtract($software);

            while ($extractor->valid()) {
                $file = $extractor->current();
                \assert($file instanceof \SplFileInfo);

                $to = $this->shouldBeExtracted($file, $files);

                if ($to === null || !$this->checkExisting($to)) {
                    $extractor->next();
                    continue;
                }

                $extractor->send($to);

                // Success
                $path = $to->getRealPath() ?: $to->getPathname();
                $this->output->writeln(
                    \sprintf(
                        '%s (<comment>%s</comment>) has been installed into <info>%s</info>',
                        $to->getFilename(),
                        $downloadResult->version,
                        $path,
                    ),
                );

                $to->isExecutable() or @\chmod($path, 0755);
            }
        };
    }

    /**
     * Checks if a file already exists and prompts for confirmation to overwrite.
     *
     * @param \SplFileInfo $bin Target file information
     * @return bool True if the file should be extracted, false otherwise
     */
    private function checkExisting(\SplFileInfo $bin): bool
    {
        if (\is_file($bin->getPathname())) {
            $this->io->warning('File already exists: ' . $bin->getPathname());
            if (!$this->io->confirm('Do you want overwrite it?', false)) {
                $this->io->note('Skipping ' . $bin->getFilename() . ' installation...');
                return false;
            }
        }

        return true;
    }

    /**
     * Determines the target path for an extracted file based on file mapping configurations.
     *
     * @param \SplFileInfo $source Source file from the archive
     * @param list<File> $mapping File mapping configurations
     * @return \SplFileInfo|null Target file path or null if file should not be extracted
     */
    private function shouldBeExtracted(\SplFileInfo $source, array $mapping): ?\SplFileInfo
    {
        $path = $this->configDestination->path ?? \getcwd();

        foreach ($mapping as $conf) {
            if (\preg_match($conf->pattern, $source->getFilename())) {
                $newName = match (true) {
                    $conf->rename === null => $source->getFilename(),
                    $source->getExtension() === '' => $conf->rename,
                    default => $conf->rename . '.' . $source->getExtension(),
                };

                return new \SplFileInfo($path . DIRECTORY_SEPARATOR . $newName);
            }
        }

        return null;
    }

    /**
     * @return File[]
     */
    private function filesToExtract(Software $software): array
    {
        $files = $software->files;
        if ($software->binary !== null) {
            $binary = new File();
            $binary->pattern = $software->binary->pattern ?? "/{$software->binary->name}{$this->os->getBinaryExtension()}/";
            $binary->rename = $software->binary->name;
            $files[] = $binary;
        }

        return $files;
    }
}
