<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\Downloader\Task;

use Internal\DLoad\Module\Config\Schema\Embed\Software;
use Internal\DLoad\Module\Task\Progress;
use React\Promise\PromiseInterface;

/**
 * Represents an executable download task.
 *
 * Contains all the information needed to execute a download operation,
 * including handler function and progress callback.
 */
final class DownloadTask
{
    /**
     * Creates a new download task.
     *
     * @param Software $software Software package configuration
     * @param \Closure(Progress): mixed $onProgress Callback to report progress.
     *        Exception thrown in this callback will stop and revert the task.
     * @param \Closure(): PromiseInterface<DownloadResult> $handler Function that executes the download
     */
    public function __construct(
        public readonly Software $software,
        public readonly \Closure $onProgress,
        public readonly \Closure $handler,
    ) {}
}
