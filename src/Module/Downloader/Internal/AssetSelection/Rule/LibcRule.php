<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\Downloader\Internal\AssetSelection\Rule;

use Internal\DLoad\Module\Common\Libc;
use Internal\DLoad\Module\Downloader\Internal\AssetSelection\AssetRule;
use Internal\DLoad\Module\Downloader\Internal\AssetSelection\Candidate;
use Internal\DLoad\Module\Downloader\Internal\AssetSelection\Selection;

/**
 * Prefers assets linked against the host libc.
 *
 * Ranks rather than removes: a static build runs whatever the libc, and some tools publish
 * only a musl build for Linux. An asset that names no libc counts as a glibc one.
 *
 * @internal
 * @psalm-internal Internal\DLoad\Module\Downloader
 */
final class LibcRule implements AssetRule
{
    public function __construct(
        private readonly Libc $libc,
    ) {}

    public function select(Selection $selection, callable $next): Selection
    {
        return $next($selection->prefer(
            'libc',
            fn(Candidate $candidate): bool => ($candidate->name->libc ?? Libc::Gnu) === $this->libc,
        ));
    }
}
