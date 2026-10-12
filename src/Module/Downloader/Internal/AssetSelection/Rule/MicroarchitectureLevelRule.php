<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\Downloader\Internal\AssetSelection\Rule;

use Internal\DLoad\Module\Downloader\Internal\AssetSelection\AssetRule;
use Internal\DLoad\Module\Downloader\Internal\AssetSelection\Candidate;
use Internal\DLoad\Module\Downloader\Internal\AssetSelection\Selection;

/**
 * Prefers the lowest x86-64 microarchitecture level, like `amd64` or `amd64_v1` over `amd64_v3`.
 *
 * The host CPU features are not checked, so a level above v1 may not run: such a build is ranked
 * lower rather than removed, and is still picked when the release has nothing else for the host.
 *
 * @internal
 * @psalm-internal Internal\DLoad\Module\Downloader
 */
final class MicroarchitectureLevelRule implements AssetRule
{
    public function select(Selection $selection, callable $next): Selection
    {
        return $next($selection->rank('level', static fn(Candidate $candidate): int => $candidate->name->level - 1));
    }
}
