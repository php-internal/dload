<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\Downloader\Internal\AssetSelection\Rule;

use Internal\DLoad\Module\Downloader\Internal\AssetSelection\AssetRule;
use Internal\DLoad\Module\Downloader\Internal\AssetSelection\Candidate;
use Internal\DLoad\Module\Downloader\Internal\AssetSelection\Selection;

/**
 * Removes OS packages (`.deb`, `.rpm`, `.msi`…) when a binary is expected.
 *
 * dload cannot unpack a package, so the binary is never found inside one. A selected package
 * downloads fine and then fails the installation, which runs after the release loop is over:
 * older releases with an archive would never be tried. Removed here, a release with only
 * a package for the host has no matching asset, and the downloader moves on to the next one.
 *
 * @internal
 * @psalm-internal Internal\DLoad\Module\Downloader
 */
final class PackageRule implements AssetRule
{
    /** Reason the packages are removed under, see {@see Selection::$removed}. */
    public const KEY = 'package';

    private const EXTENSIONS = [
        'deb', 'rpm', 'apk', 'msi', 'dmg', 'pkg',
        'snap', 'flatpak', 'msix', 'msixbundle', 'appx', 'appxbundle', 'nupkg',
    ];

    public function select(Selection $selection, callable $next): Selection
    {
        $selection->strict and $selection = $selection->remove(
            static fn(Candidate $candidate): bool => $candidate->hasExtension(self::EXTENSIONS),
            self::KEY,
        );

        return $next($selection);
    }
}
