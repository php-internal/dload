<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\Downloader\Exception;

/**
 * Software could not be downloaded from any of the configured repositories.
 *
 * The message contains a report of every attempt, so it is intended to be shown to the user as is.
 *
 * @internal
 */
final class DownloadFailed extends \RuntimeException
{
    /**
     * @param non-empty-string $software Software identifier
     * @param non-empty-string $report Human-readable report of all download attempts
     */
    public function __construct(
        public readonly string $software,
        public readonly string $report,
        ?\Throwable $previous = null,
    ) {
        parent::__construct(
            \sprintf("Failed to download `%s`.\n%s", $software, $report),
            0,
            $previous,
        );
    }
}
