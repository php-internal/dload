<?php

declare(strict_types=1);

namespace Internal\DLoad;

use Internal\DLoad\Module\Archive\ArchiveEntryPath;
use Internal\DLoad\Module\Archive\ArchiveFactory;
use Internal\DLoad\Module\Binary\BinaryProvider;
use Internal\DLoad\Module\Common\DloadResult;
use Internal\DLoad\Module\Common\FileSystem\FS;
use Internal\DLoad\Module\Common\Input\Destination;
use Internal\DLoad\Module\Common\OperatingSystem;
use Internal\DLoad\Module\Config\Schema\Action\Download as DownloadConfig;
use Internal\DLoad\Module\Config\Schema\Action\Type;
use Internal\DLoad\Module\Config\Schema\Embed\Binary as BinaryConfig;
use Internal\DLoad\Module\Config\Schema\Embed\File;
use Internal\DLoad\Module\Config\Schema\Embed\Software;
use Internal\DLoad\Module\Downloader\Downloader;
use Internal\DLoad\Module\Downloader\Exception\NothingExtracted;
use Internal\DLoad\Module\Downloader\SoftwareCollection;
use Internal\DLoad\Module\Downloader\Task\DownloadResult;
use Internal\DLoad\Module\Downloader\Task\DownloadTask;
use Internal\DLoad\Module\Task\Manager;
use Internal\DLoad\Module\Version\Constraint;
use Internal\DLoad\Module\Version\Version;
use Internal\DLoad\Service\Logger;
use Internal\Path;
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
 *  $dload = $container->get(DLoad::class);
 *  $dload->addTask(new DownloadConfig('rr', '^2.12.0'));
 *  $dload->run();
 * ```
 *
 * @internal
 */
final class DLoad
{
    /** @var bool Flag to use mock data instead of actual downloads for testing */
    public bool $useMock = false;

    /** @var \SplFileInfo|null Overrides the asset returned when {@see self::$useMock} is set (tests only) */
    public ?\SplFileInfo $mockArchive = null;

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
        if (!$this->useMock) {
            return $this->downloader->download($software, $action, static fn() => null);
        }

        $mockFile = $this->mockArchive
            ?? new \SplFileInfo(Info::ROOT_DIR . '/resources/mock/roadrunner-2024.1.5-windows-amd64.zip');

        return new DownloadTask(
            $software,
            static fn() => null,
            static fn(): PromiseInterface => resolve(
                new DownloadResult($mockFile, Version::fromVersionString('2024.1.5')),
            ),
        );
    }

    /**
     * Creates a closure to handle extraction of downloaded files.
     *
     * @param Software $software Software package configuration
     * @param DownloadConfig $action Download action configuration
     * @return DloadResult Result of the extraction process containing extracted files and binary
     */
    private function prepareExtractTask(
        DownloadResult $downloadResult,
        Software $software,
        DownloadConfig $action,
    ): DloadResult {
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

            # Archive type: unpack the whole archive into the destination, preserving the
            # internal directory structure instead of flattening matched files into one folder.
            if ($action->type === Type::Archive) {
                return $this->extractArchive($downloadResult, $software, $destination);
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
            $extractionRules = $this->describeExtractionRules($software, $binaryPattern);
            $archiveFiles = [];

            while ($extractor->valid()) {
                $to = $rule = null;
                $file = $extractor->current();
                \assert($file instanceof \SplFileInfo);
                $archiveFiles[] = $file->getFilename();

                # Check if it's binary and should be extracted
                $isBinary = false;
                if ($binaryPattern !== null) {
                    [$to, $rule] = $this->shouldBeExtracted($file, [$binaryPattern], $destination);
                    $isBinary = $to !== null;
                }

                isset($to) or [$to, $rule] = $this->shouldBeExtracted($file, $software->files, $destination);
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

                \assert(isset($rule));
                $rule->chmod === null or @\chmod($path, $rule->chmod);

                # Add files and binary to result
                $path = Path::create($path);
                $resultFiles[] = $path;
                if ($isBinary) {
                    $resultBinary = $this->binaryProvider->getLocalBinary($path->parent(), $software->binary);
                    $binaryPattern = null;
                }
            }

            # A downloaded asset without a single matching file means nothing was installed
            $resultFiles === [] and throw new NothingExtracted(
                assetName: $fileInfo->getFilename(),
                rules: $extractionRules,
                files: $archiveFiles,
            );

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
                $extension = $source->getExtension();
                // Validate that the "extension" looks like a real file extension
                // (short, alphanumeric only — e.g. "exe", "phar", "gz")
                // and not a version/platform artifact like "0-linux-amd64"
                $hasRealExtension = $extension !== '' && \preg_match('/^(?=.*[a-zA-Z])[a-zA-Z0-9]{1,10}$/', $extension) === 1;

                $newName = match (true) {
                    $conf->rename === null => $source->getFilename(),
                    !$hasRealExtension => $conf->rename,
                    default => $conf->rename . '.' . $extension,
                };

                return [new \SplFileInfo((string) $path->join($newName)), $conf];
            }
        }

        return [null, null];
    }

    /**
     * Extracts the whole archive into the destination, preserving the internal directory structure.
     *
     * Unlike the flat extraction used for single-binary tools, this keeps relative paths intact,
     * which is required for archives whose files reference each other by relative path
     * (e.g. a binary resolving a shared library via an `$ORIGIN/../lib` rpath).
     *
     * When a single top-level directory wraps the whole archive, it is stripped (like
     * `tar --strip-components=1`). When `<file>` rules are defined, they act as an include filter
     * (matched by file name); otherwise every entry is extracted. A configured `<binary>` is only
     * used to locate the executable inside the extracted tree for version checks — it is not moved.
     *
     * @return DloadResult Result of the extraction process containing extracted files and binary
     * @throws NothingExtracted When the archive turned out to be empty or nothing matched the filters
     */
    private function extractArchive(
        DownloadResult $downloadResult,
        Software $software,
        Path $destination,
    ): DloadResult {
        $fileInfo = $downloadResult->file;
        $archive = $this->archiveFactory->create($fileInfo);
        $this->logger->info('Extracting %s (preserving structure)', $fileInfo->getFilename());

        # List entry paths (without extracting) to detect a single wrapping directory to strip
        $entries = $archive->entries();
        $stripPrefix = ArchiveEntryPath::commonTopLevelDirectory($entries);

        $binaryRule = $this->generateBinaryExtractionConfig($software->binary);
        $hasFilters = $software->files !== [];

        $resultFiles = [];
        $resultBinary = null;

        # Extract entries to their relative destinations
        $extractor = $archive->extract();
        while ($extractor->valid()) {
            $relativePath = $extractor->key();
            $file = $extractor->current();
            \assert($file instanceof \SplFileInfo);

            $target = $this->resolveArchiveTarget($relativePath, $stripPrefix, $destination);
            if ($target === null) {
                $this->logger->debug('Skipping archive entry `%s`.', $relativePath);
                $extractor->next();
                continue;
            }

            $isBinary = $binaryRule !== null && \preg_match($binaryRule->pattern, $file->getFilename()) === 1;
            $matchedFilter = $hasFilters ? $this->matchFileRule($file, $software->files) : null;

            # With explicit <file> rules, extract only matching entries (the binary is always kept)
            if ($hasFilters && $matchedFilter === null && !$isBinary) {
                $this->logger->debug('Skipping file `%s`.', $relativePath);
                $extractor->next();
                continue;
            }

            $this->logger->debug('Extracting %s to %s...', $relativePath, (string) $target);
            FS::mkdir($target->parent());
            # `send()` performs the extraction and already advances the generator to the next entry,
            # so this branch must not call `next()` afterwards.
            $extractor->send(new \SplFileInfo((string) $target));

            # Binaries get the executable bit; files honor their configured chmod
            $chmod = $isBinary ? 0o755 : $matchedFilter?->chmod;
            $chmod === null or @\chmod((string) $target, $chmod);

            $resultFiles[] = $target;

            if ($isBinary && $resultBinary === null && $software->binary !== null) {
                # Locate the binary inside the extracted tree for version checks; keep it in place
                $resultBinary = $this->binaryProvider->getLocalBinary($target->parent(), $software->binary);
            }
        }

        $resultFiles === [] and throw new NothingExtracted(
            assetName: $fileInfo->getFilename(),
            rules: $this->describeExtractionRules($software, $binaryRule),
            files: $entries,
        );

        $this->output->writeln(
            \sprintf(
                '<comment>%d</comment> file(s) from <comment>%s</comment> have been installed into <info>%s</info>',
                \count($resultFiles),
                $downloadResult->version,
                (string) $destination,
            ),
        );

        return new DloadResult($resultFiles, $resultBinary);
    }

    /**
     * Resolves the extraction target for an archive entry, stripping the common wrapping directory
     * and guarding against path traversal (zip-slip).
     *
     * @param non-empty-string $relativePath Entry path relative to the archive root (forward slashes)
     * @param string $stripPrefix Leading directory to remove (e.g. `package-1.0/`), or an empty string
     * @return Path|null Target path, or null when the entry should be skipped
     */
    private function resolveArchiveTarget(string $relativePath, string $stripPrefix, Path $destination): ?Path
    {
        $relative = ArchiveEntryPath::relative($relativePath, $stripPrefix);

        return $relative === null ? null : $destination->join($relative);
    }

    /**
     * Finds the first `<file>` rule whose pattern matches the given archive entry by its file name.
     *
     * @param list<File> $filters File mapping configurations
     */
    private function matchFileRule(\SplFileInfo $file, array $filters): ?File
    {
        foreach ($filters as $filter) {
            if (\preg_match($filter->pattern, $file->getFilename()) === 1) {
                return $filter;
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
     * Lists the patterns applied to archive entries, to explain why nothing was extracted.
     *
     * @param File|null $binaryPattern Generated binary extraction rule
     * @return list<string>
     */
    private function describeExtractionRules(Software $software, ?File $binaryPattern): array
    {
        $rules = [];
        $binaryPattern === null or $rules[] = \sprintf('binary `%s`', $binaryPattern->pattern);

        foreach ($software->files as $file) {
            $rules[] = \sprintf('file `%s`', $file->pattern);
        }

        return $rules;
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
