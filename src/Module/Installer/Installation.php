<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\Installer;

use Internal\DLoad\Module\Common\FileSystem\FS;
use Internal\DLoad\Module\Config\Schema\Action\Type;
use Internal\DLoad\Module\Config\Schema\Embed\File;
use Internal\DLoad\Module\Config\Schema\Embed\Software;
use Internal\DLoad\Module\Downloader\Task\DownloadResult;
use Internal\Path;

/**
 * A downloaded asset on its way into the destination directory.
 *
 * @internal
 * @psalm-internal Internal\DLoad\Module\Installer
 */
final class Installation
{
    /**
     * @param Type|null $type Download action type; null when the action does not restrict it.
     * @param File|null $binaryRule Extraction rule of the software binary, null when it has none.
     */
    public function __construct(
        public readonly DownloadResult $download,
        public readonly Software $software,
        public readonly ?Type $type,
        public readonly Path $destination,
        public readonly ?File $binaryRule,
    ) {}

    /**
     * Lists the patterns applied to archive entries, to explain why nothing was extracted.
     *
     * @return list<string>
     */
    public function describeRules(): array
    {
        $rules = [];
        $this->binaryRule === null or $rules[] = \sprintf('binary `%s`', $this->binaryRule->pattern);

        foreach ($this->software->files as $file) {
            $rules[] = \sprintf('file `%s`', $file->pattern);
        }

        return $rules;
    }

    /**
     * Moves the downloaded file into the destination under its own name.
     */
    public function moveToDestination(): Path
    {
        $file = $this->download->file;
        $toFile = $this->destination->join($file->getFilename());
        FS::moveFile(Path::create($file->getRealPath() ?: $file->getPathname()), $toFile);

        return $toFile;
    }
}
