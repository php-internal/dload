<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\Downloader\Internal;

use Internal\DLoad\Module\Common\Config\Embed\Repository;
use Internal\DLoad\Module\Common\Config\Embed\Software;
use Internal\DLoad\Module\Downloader\Progress;

final class DownloadContext
{
    /** Current repository config */
    public Repository $repoConfig;

    /** Downloaded file */
    public \SplFileObject $file;

    /**
     * @param \Closure(Progress): mixed $onProgress Callback to report progress.
     *        Exception thrown in this callback will stop and revert the task.
     */
    public function __construct(
        public readonly Software $software,
        public readonly \Closure $onProgress,
    ) {}
}
