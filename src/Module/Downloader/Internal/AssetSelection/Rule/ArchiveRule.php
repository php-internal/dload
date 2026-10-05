<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\Downloader\Internal\AssetSelection\Rule;

use Internal\DLoad\Module\Archive\ArchiveFactory;
use Internal\DLoad\Module\Downloader\Internal\AssetSelection\AssetRule;
use Internal\DLoad\Module\Downloader\Internal\AssetSelection\Candidate;
use Internal\DLoad\Module\Downloader\Internal\AssetSelection\Selection;

/**
 * Prefers archives dload can extract over other files.
 *
 * @internal
 * @psalm-internal Internal\DLoad\Module\Downloader
 */
final class ArchiveRule implements AssetRule
{
    public function __construct(
        private readonly ArchiveFactory $archiveFactory,
    ) {}

    public function select(Selection $selection, callable $next): Selection
    {
        $extensions = $this->archiveFactory->getSupportedExtensions();

        return $next($selection->prefer(static fn(Candidate $candidate): bool => $candidate->hasExtension($extensions)));
    }
}
