<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\Installer;

use Internal\DLoad\Module\Archive\ArchiveFactory;
use Internal\DLoad\Module\Binary\BinaryProvider;
use Internal\DLoad\Module\Common\DloadResult;
use Internal\DLoad\Module\Common\OperatingSystem;
use Internal\DLoad\Module\Common\Pipeline\Pipeline;
use Internal\DLoad\Module\Config\Schema\Action\Type;
use Internal\DLoad\Module\Config\Schema\Embed\Binary as BinaryConfig;
use Internal\DLoad\Module\Config\Schema\Embed\File;
use Internal\DLoad\Module\Config\Schema\Embed\Software;
use Internal\DLoad\Module\Downloader\Exception\NothingExtracted;
use Internal\DLoad\Module\Downloader\Task\DownloadResult;
use Internal\DLoad\Module\Installer\Internal\InstallStep;
use Internal\DLoad\Module\Installer\Internal\RuleExtraction;
use Internal\DLoad\Module\Installer\Internal\Step\ArchiveStep;
use Internal\DLoad\Module\Installer\Internal\Step\PharStep;
use Internal\DLoad\Module\Installer\Internal\Step\PlainFileStep;
use Internal\DLoad\Module\Installer\Internal\Step\WorkspaceStep;
use Internal\DLoad\Service\Logger;
use Internal\Path;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Installs a downloaded asset into the destination directory.
 *
 * ```php
 * $result = $installer->install($download, $software, Type::Archive, Path::create('bin'));
 * ```
 *
 * @internal
 */
final class Installer
{
    /** @var callable(Installation): DloadResult */
    private $pipeline;

    public function __construct(
        Logger $logger,
        OutputInterface $output,
        ArchiveFactory $archiveFactory,
        BinaryProvider $binaryProvider,
        private readonly OperatingSystem $os,
    ) {
        /**
         * The first step that recognizes the download installs it; the order is the priority.
         *
         * @see InstallStep::install()
         * @var callable(Installation): DloadResult $pipeline
         */
        $pipeline = Pipeline::prepare(
            new WorkspaceStep($logger),
            new PharStep($logger),
            new ArchiveStep($logger, $output, $archiveFactory, $binaryProvider),
            new PlainFileStep($logger),
        )->with(new RuleExtraction($logger, $output, $archiveFactory, $binaryProvider), 'install');
        $this->pipeline = $pipeline;
    }

    /**
     * @param Type|null $type Download action type; null when the action does not restrict it.
     * @param bool $temporary Whether the downloaded file is removed once installed.
     * @throws NothingExtracted When nothing in the download matched the extraction rules
     */
    public function install(
        DownloadResult $download,
        Software $software,
        ?Type $type,
        Path $destination,
        bool $temporary = true,
    ): DloadResult {
        return ($this->pipeline)(new Installation(
            download: $download,
            software: $software,
            type: $type,
            destination: $destination,
            binaryRule: $this->binaryRule($software->binary),
            temporary: $temporary,
        ));
    }

    /**
     * Generates the extraction rule of the software binary.
     */
    private function binaryRule(?BinaryConfig $binary): ?File
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
