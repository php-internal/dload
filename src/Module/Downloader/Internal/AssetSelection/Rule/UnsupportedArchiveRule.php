<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\Downloader\Internal\AssetSelection\Rule;

use Internal\DLoad\Module\Archive\ArchiveFactory;
use Internal\DLoad\Module\Downloader\Internal\AssetSelection\AssetRule;
use Internal\DLoad\Module\Downloader\Internal\AssetSelection\Candidate;
use Internal\DLoad\Module\Downloader\Internal\AssetSelection\Selection;

/**
 * Removes archives dload has no extractor for (`.tar.xz`, `.7z`, `.zst`…) when a binary is expected.
 *
 * Like an OS package, see {@see PackageRule}, such an archive downloads fine and then fails
 * the installation after the release loop is over. Removed here, a release with only such
 * an archive for the host has no matching asset, and the downloader moves on to the next one.
 *
 * Only the known archive formats are removed: a name with another extension or none is taken
 * for a raw binary.
 *
 * @internal
 * @psalm-internal Internal\DLoad\Module\Downloader
 */
final class UnsupportedArchiveRule implements AssetRule
{
    /** Reason the archives are removed under, see {@see Selection::$removed}. */
    public const KEY = 'unsupported-archive';

    /**
     * Archive and compression formats, the supported ones included: the formats an extractor
     * is added for stop being removed without a change here.
     */
    private const EXTENSIONS = [
        'zip', 'gz', 'tgz', 'tar', 'xz', 'txz', 'bz2', 'tbz', 'tbz2',
        'zst', 'tzst', 'lz', 'lzma', 'lz4', '7z', 'rar',
    ];

    public function __construct(
        private readonly ArchiveFactory $archiveFactory,
    ) {}

    public function select(Selection $selection, callable $next): Selection
    {
        $supported = $this->archiveFactory->getSupportedExtensions();

        $selection->strict and $selection = $selection->remove(
            static fn(Candidate $candidate): bool => $candidate->hasExtension(self::EXTENSIONS)
                && !$candidate->hasExtension($supported),
            self::KEY,
        );

        return $next($selection);
    }
}
