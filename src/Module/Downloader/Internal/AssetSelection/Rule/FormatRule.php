<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\Downloader\Internal\AssetSelection\Rule;

use Internal\DLoad\Module\Archive\ArchiveFactory;
use Internal\DLoad\Module\Config\Schema\Action\Type;
use Internal\DLoad\Module\Downloader\Internal\AssetSelection\AssetRule;
use Internal\DLoad\Module\Downloader\Internal\AssetSelection\Candidate;
use Internal\DLoad\Module\Downloader\Internal\AssetSelection\Selection;

/**
 * Removes assets of a format the download action does not accept.
 *
 * @internal
 * @psalm-internal Internal\DLoad\Module\Downloader
 */
final class FormatRule implements AssetRule
{
    public function __construct(
        private readonly ArchiveFactory $archiveFactory,
    ) {}

    public function select(Selection $selection, callable $next): Selection
    {
        $extensions = match ($selection->type) {
            Type::Phar => ['phar'],
            Type::Archive => $this->archiveFactory->getSupportedExtensions(),
            default => null,
        };

        $extensions === null or $selection = $selection->remove(
            static fn(Candidate $candidate): bool => !$candidate->hasExtension($extensions),
        );

        return $next($selection);
    }
}
