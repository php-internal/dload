<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\Downloader\Task;

final class DownloadResult
{
    /**
     * @param non-empty-string $version
     */
    public function __construct(
        public readonly \SplFileInfo $file,
        public readonly string $version,
    ) {}
}
