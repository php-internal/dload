<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\Downloader;

/**
 * Represents download progress information.
 *
 * Immutable data object containing download progress metrics and status message.
 * Used for reporting progress to listeners during download operations.
 */
final class Progress
{
    public function __construct(
        /** @var int<0, max> Total size in bytes */
        public readonly int $total = 100,
        /** @var int<0, max> Current progress in bytes */
        public readonly int $current = 0,
        /** @var string Status message */
        public readonly string $message = '',
    ) {}
}
