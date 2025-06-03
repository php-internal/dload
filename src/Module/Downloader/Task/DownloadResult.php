<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\Downloader\Task;

use Internal\DLoad\Module\Version\Version;

/**
 * Represents the result of a successful download operation.
 *
 * Contains the downloaded file reference and version information.
 */
final class DownloadResult
{
    /**
     * Creates a new download result.
     *
     * @param \SplFileInfo $file Downloaded file information
     * @param Version $version Version of the downloaded software
     */
    public function __construct(
        public readonly \SplFileInfo $file,
        public readonly Version $version,
    ) {}
}
