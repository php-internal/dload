<?php

declare(strict_types=1);

namespace Internal\DLoad;

use Internal\DLoad\Module\Archive\ArchiveFactory;
use Internal\DLoad\Module\Binary\BinaryProvider;
use Internal\DLoad\Module\Common\DloadResult;
use Internal\DLoad\Module\Common\FileSystem\FS;
use Internal\DLoad\Module\Common\FileSystem\Path;
use Internal\DLoad\Module\Common\Input\Destination;
use Internal\DLoad\Module\Common\OperatingSystem;
use Internal\DLoad\Module\Config\Schema\Action\Download as DownloadConfig;
use Internal\DLoad\Module\Config\Schema\Action\Type;
use Internal\DLoad\Module\Config\Schema\Embed\Binary as BinaryConfig;
use Internal\DLoad\Module\Config\Schema\Embed\File;
use Internal\DLoad\Module\Config\Schema\Embed\Software;
use Internal\DLoad\Module\Downloader\Downloader;
use Internal\DLoad\Module\Downloader\SoftwareCollection;
use Internal\DLoad\Module\Downloader\Task\DownloadResult;
use Internal\DLoad\Module\Downloader\Task\DownloadTask;
use Internal\DLoad\Module\Task\Manager;
use Internal\DLoad\Module\Version\Constraint;
use Internal\DLoad\Module\Version\Version;
use Internal\DLoad\Service\Logger;
use React\Promise\PromiseInterface;
use Symfony\Component\Console\Output\OutputInterface;

use function React\Async\await;
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
        private readonly Manager $taskManager,
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
     *
     * @return PromiseInterface<DloadResult> Resolves after the download task is finished.
     *
     * @throws \RuntimeException When software package is not found
     */
    public function addTask(DownloadConfig $action, bool $force = false): PromiseInterface
    {
        // Find Software
        $software = $this->softwareCollection->findSoftware($action->software) ?? throw new \RuntimeException(
            "Software `{$action->software}` not found in registry.",
        );

        // Check if binary already exists and satisfies version constraint
        $destinationPath = $this->getDestinationPath($action);
        $type = $action->type;

        if (!$force && ($type === null || $type === Type::Binary) && $software->binary !== null) {
            // Check different constraints
            $binary = $this->binaryProvider->getLocalBinary($destinationPath, $software->binary, $software->name);

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
                return resolve(DloadResult::fromBinary($binary));
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
                return resolve(DloadResult::fromBinary($binary));
            }

            // Download a newer version only if the version is specified
            if ($version !== null) {
                // todo
            }
        }

        add_task:

        return $this->taskManager->addTask(function () use ($software, $action): DloadResult {
            // Create a Download task
            $task = $this->prepareDownloadTask($software, $action);

            // Extract files
            $extraction = ($task->handler)()->then(
                fn(DownloadResult $result): DloadResult => $this->prepareExtractTask($result, $software, $action),
            );

            return await($extraction);
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
    private function prepareExtractTask(DownloadResult $downloadResult, Software $software, DownloadConfig $action): DloadResult
    {
        $fileInfo = $downloadResult->file;
        $tempFilePath = Path::create($fileInfo->getRealPath() ?: $fileInfo->getPathname());
        $resultFiles = [];
        $resultBinary = null;

        try {
            # Create destination directory if it doesn't exist
            $destination = $this->getDestinationPath($action);
            FS::mkdir($destination);

            # In PHAR actions, we do not extract files, just copy the downloaded file
            if ($action->type === Type::Phar) {
                $this->logger->debug(
                    'Copying downloaded file `%s` to destination as a PHAR archive.',
                    $fileInfo->getFilename(),
                );
                $toFile = $destination->join($fileInfo->getFilename());
                FS::moveFile($tempFilePath, $toFile);
                \chmod((string) $toFile, 0o755);

                # todo: add PHAR binary to result
                return new DloadResult([$toFile]);
            }

            # If no extraction rules are defined, do not extract anything
            # and just copy the file to the destination
            if ($software->files === [] && $software->binary === null) {
                $this->logger->debug(
                    'No files to extract for `%s`, copying the downloaded file to the destination.',
                    $fileInfo->getFilename(),
                );
                $toFile = $destination->join($fileInfo->getFilename());
                FS::moveFile($tempFilePath, $toFile);

                return new DloadResult([$toFile]);
            }

            $archive = $this->archiveFactory->create($fileInfo);
            $extractor = $archive->extract();
            $this->logger->info('Extracting %s', $fileInfo->getFilename());
            $binaryPattern = $this->generateBinaryExtractionConfig($software->binary);

            while ($extractor->valid()) {
                $file = $extractor->current();
                \assert($file instanceof \SplFileInfo);

                # Check if it's binary and should be extracted
                $isBinary = false;
                if ($binaryPattern !== null) {
                    [$to, $rule] = $this->shouldBeExtracted($file, [$binaryPattern], $destination);
                    $isBinary = $to !== null;
                }

                $isBinary or [$to, $rule] = $this->shouldBeExtracted($file, $software->files, $destination);
                if ($to === null) {
                    $this->logger->debug('Skipping file `%s`.', $file->getFilename());
                    $extractor->next();
                    continue;
                }

                $this->logger->debug('Extracting %s to %s...', $file->getFilename(), $to->getPathname());

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

                \assert($rule !== null);
                $rule->chmod === null or @\chmod($path, $rule->chmod);

                # Add files and binary to result
                $path = Path::create($path);
                $resultFiles[] = $path;
                if ($isBinary) {
                    $resultBinary = $this->binaryProvider->getLocalBinary($path->parent(), $software->binary);
                    $binaryPattern = null;
                }
            }

            return new DloadResult($resultFiles, $resultBinary);
        } finally {
            // Cleanup: Delete the temporary downloaded file
            if (!$this->useMock && $tempFilePath->exists()) {
                $this->logger->debug('Cleaning up temporary file: %s', $tempFilePath->__toString());
                FS::remove($tempFilePath);
            }
        }
    }

    /**
     * Determines the target path for an extracted file based on file mapping configurations.
     *
     * @param \SplFileInfo $source Source file from the archive
     * @param list<File> $mapping File mapping configurations
     * @param Path $path Destination path where files should be extracted
     * @return array{\SplFileInfo, File}|array{null, null} Array containing:
     *         - Target file path or null if file should not be extracted
     *         - File configuration that matched the source file, or null if no match found
     */
    private function shouldBeExtracted(\SplFileInfo $source, array $mapping, Path $path): array
    {
        foreach ($mapping as $conf) {
            if (\preg_match($conf->pattern, $source->getFilename())) {
                $newName = match (true) {
                    $conf->rename === null => $source->getFilename(),
                    $source->getExtension() === '' => $conf->rename,
                    default => $conf->rename . '.' . $source->getExtension(),
                };

                return [new \SplFileInfo((string) $path->join($newName)), $conf];
            }
        }

        return [null, null];
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
     * Generates a binary extraction configuration based on the provided binary configuration.
     *
     * @param BinaryConfig|null $binary Binary configuration object
     * @return File|null File extraction configuration or null if no binary is provided
     */
    private function generateBinaryExtractionConfig(?BinaryConfig $binary): ?File
    {
        if ($binary === null) {
            return null;
        }

        $result = new File();
        $result->pattern = $binary->pattern
            ?? "/^{$binary->name}{$this->os->getBinaryExtension()}$/";
        $result->rename = $binary->name;
        $result->chmod = 0o755; // Default permissions for binaries

        return $result;
    }
}
