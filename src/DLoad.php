<?php

declare(strict_types=1);

namespace Internal\DLoad;

use Internal\DLoad\Module\Archive\ArchiveFactory;
use Internal\DLoad\Module\Common\Config\Action\Download as DownloadConfig;
use Internal\DLoad\Module\Common\Config\Embed\File;
use Internal\DLoad\Module\Common\Config\Embed\Software;
use Internal\DLoad\Module\Common\Input\Destination;
use Internal\DLoad\Module\Downloader\Downloader;
use Internal\DLoad\Module\Downloader\SoftwareCollection;
use Internal\DLoad\Module\Downloader\Task\DownloadResult;
use Internal\DLoad\Module\Downloader\Task\DownloadTask;
use Internal\DLoad\Module\Downloader\TaskManager;
use React\Promise\PromiseInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\StyleInterface;

use function React\Promise\resolve;

/**
 * To have a short syntax.
 *
 * @internal
 */
final class DLoad
{
    public bool $useMock = false;

    public function __construct(
        private readonly TaskManager $taskManager,
        private readonly SoftwareCollection $softwareCollection,
        private readonly Downloader $downloader,
        private readonly ArchiveFactory $archiveFactory,
        private readonly Destination $configDestination,
        private readonly OutputInterface $output,
        private readonly StyleInterface $io,
    ) {}

    public function addTask(DownloadConfig $action): void
    {
        $this->taskManager->addTask(function () use ($action): void {
            // Find Software
            $software = $this->softwareCollection->findSoftware($action->software) ?? throw new \RuntimeException(
                'Software not found.',
            );

            // Create a Download task
            $task = $this->prepareDownloadTask($software, $action);

            // Extract files
            ($task->handler)()->then($this->prepareExtractTask($software));
        });
    }

    public function run(): void
    {
        $this->taskManager->await();
    }

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
     * @return \Closure(DownloadResult): void
     */
    private function prepareExtractTask(Software $software): \Closure
    {
        return function (DownloadResult $downloadResult) use ($software): void {
            $fileInfo = $downloadResult->file;
            $archive = $this->archiveFactory->create($fileInfo);
            $extractor = $archive->extract();

            while ($extractor->valid()) {
                $file = $extractor->current();
                \assert($file instanceof \SplFileInfo);

                $to = $this->shouldBeExtracted($file, $software->files);

                if ($to === null || !$this->checkExisting($to)) {
                    $extractor->next();
                    continue;
                }

                $extractor->send($to);

                // Success
                $path = $to->getRealPath() ?: $to->getPathname();
                $this->output->writeln(\sprintf(
                    '%s (<comment>%s</comment>) has been installed into <info>%s</info>',
                    $to->getFilename(),
                    $downloadResult->version,
                    $path,
                ));

                $to->isExecutable() or @\chmod($path, 0755);
            }
        };
    }

    /**
     * @return bool True if the file should be extracted, false otherwise.
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
     * @param list<File> $mapping
     */
    private function shouldBeExtracted(\SplFileInfo $source, array $mapping): ?\SplFileInfo
    {
        $path = $this->configDestination->path ?? \getcwd();

        foreach ($mapping as $conf) {
            if (\preg_match($conf->pattern, $source->getFilename())) {
                $newName = match(true) {
                    $conf->rename === null => $source->getFilename(),
                    $source->getExtension() === '' => $conf->rename,
                    default => $conf->rename . '.' . $source->getExtension(),
                };

                return new \SplFileInfo($path . DIRECTORY_SEPARATOR . $newName);
            }
        }

        return null;
    }
}
