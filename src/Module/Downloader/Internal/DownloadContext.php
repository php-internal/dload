<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\Downloader\Internal;

use Internal\DLoad\Module\Common\Config\Action\Download as DownloadConfig;
use Internal\DLoad\Module\Common\Config\Embed\Repository;
use Internal\DLoad\Module\Common\Config\Embed\Software;
use Internal\DLoad\Module\Downloader\Progress;
use Internal\DLoad\Module\Repository\AssetInterface;
use Internal\DLoad\Module\Repository\ReleaseInterface;

final class DownloadContext
{
    /** Current repository config */
    public Repository $repoConfig;

    /** Downloaded file */
    public \SplFileObject $file;

    /** Current asset */
    public AssetInterface $asset;

    /** Current release */
    public ReleaseInterface $release;

    /**
     * @param \Closure(Progress): mixed $onProgress Callback to report progress.
     *        Exception thrown in this callback will stop and revert the task.
     */
    public function __construct(
        public readonly Software $software,
        public readonly \Closure $onProgress,
        public readonly DownloadConfig $actionConfig,
    ) {}
}
