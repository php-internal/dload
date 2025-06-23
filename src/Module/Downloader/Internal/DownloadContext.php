<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\Downloader\Internal;

use Internal\DLoad\Module\Common\FileSystem\Path;
use Internal\DLoad\Module\Config\Schema\Action\Download as DownloadConfig;
use Internal\DLoad\Module\Config\Schema\Embed\Repository;
use Internal\DLoad\Module\Config\Schema\Embed\Software;
use Internal\DLoad\Module\Downloader\Progress;
use Internal\DLoad\Module\Repository\AssetInterface;
use Internal\DLoad\Module\Repository\ReleaseInterface;

/**
 * Context object for download operations.
 *
 * Contains all contextual information needed during a download operation, including
 * configurations, selected components, and callback functions.
 *
 * @internal
 */
final class DownloadContext
{
    /** @var Repository Current repository configuration */
    public Repository $repoConfig;

    /** @var \SplFileObject Downloaded file handle */
    public \SplFileObject $file;

    /** @var AssetInterface Current asset being processed */
    public AssetInterface $asset;

    /** @var ReleaseInterface Current release being processed */
    public ReleaseInterface $release;

    /**
     * Creates a new download context.
     *
     * @param Software $software Software package configuration
     * @param \Closure(Progress): mixed $onProgress Callback to report progress.
     *        Exception thrown in this callback will stop and revert the task.
     * @param DownloadConfig $actionConfig Download action configuration
     * @param Path $tempDir Temporary directory for downloads
     */
    public function __construct(
        public readonly Software $software,
        public readonly \Closure $onProgress,
        public readonly DownloadConfig $actionConfig,
        public readonly Path $tempDir,
    ) {}
}
