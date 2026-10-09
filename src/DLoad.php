<?php

declare(strict_types=1);

namespace Internal\DLoad;

use Internal\DLoad\Module\Binary\BinaryProvider;
use Internal\DLoad\Module\Common\DloadResult;
use Internal\DLoad\Module\Common\Input\Destination;
use Internal\DLoad\Module\Config\Schema\Action\Download as DownloadConfig;
use Internal\DLoad\Module\Config\Schema\Action\Type;
use Internal\DLoad\Module\Config\Schema\Embed\Software;
use Internal\DLoad\Module\Downloader\Downloader;
use Internal\DLoad\Module\Downloader\Task\DownloadResult;
use Internal\DLoad\Module\Downloader\Task\DownloadTask;
use Internal\DLoad\Module\Installer\Installer;
use Internal\DLoad\Module\Software\SoftwareCollection;
use Internal\DLoad\Module\Task\Manager;
use Internal\DLoad\Module\Version\Constraint;
use Internal\DLoad\Module\Version\Version;
use Internal\DLoad\Service\Logger;
use Internal\Path;
use React\Promise\PromiseInterface;

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
        private readonly Installer $installer,
        private readonly Destination $configDestination,
        private readonly BinaryProvider $binaryProvider,
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
                fn(DownloadResult $result): DloadResult => $this->installer->install(
                    $result,
                    $software,
                    $action->type,
                    $this->getDestinationPath($action),
                    temporary: !$this->useMock,
                ),
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
     * Gets the destination path for file extraction, prioritizing global destination path over custom extraction path.
     *
     * @param DownloadConfig $action Download action configuration
     */
    private function getDestinationPath(DownloadConfig $action): Path
    {
        return Path::create($this->configDestination->path ?? $action->extractPath ?? (string) \getcwd());
    }
}
