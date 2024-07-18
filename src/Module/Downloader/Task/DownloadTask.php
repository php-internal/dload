<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\Downloader\Task;

use Internal\DLoad\Module\Common\Config\Embed\Software;
use React\Promise\PromiseInterface;

final class DownloadTask
{
    /**
     * @param \Closure(Progress): mixed $onProgress Callback to report progress.
     *        Exception thrown in this callback will stop and revert the task.
     * @param \Closure(): PromiseInterface<\SplFileObject> $handler
     */
    public function __construct(
        public readonly Software $software,
        public readonly \Closure $onProgress,
        public readonly \Closure $handler,
    ) {}
}
