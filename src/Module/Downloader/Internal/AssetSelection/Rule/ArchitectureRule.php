<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\Downloader\Internal\AssetSelection\Rule;

use Internal\DLoad\Module\Common\Architecture;
use Internal\DLoad\Module\Downloader\Internal\AssetSelection\AssetRule;
use Internal\DLoad\Module\Downloader\Internal\AssetSelection\Candidate;
use Internal\DLoad\Module\Downloader\Internal\AssetSelection\Selection;

/**
 * Prefers assets built for the host architecture.
 *
 * A strict selection removes the others; otherwise they are ranked last.
 *
 * @internal
 * @psalm-internal Internal\DLoad\Module\Downloader
 */
final class ArchitectureRule implements AssetRule
{
    public function __construct(
        private readonly Architecture $architecture,
    ) {}

    public function select(Selection $selection, callable $next): Selection
    {
        $fits = fn(Candidate $candidate): bool => $candidate->asset->getArchitecture() === $this->architecture;
        $selection->strict and $selection = $selection->remove(static fn(Candidate $candidate): bool => !$fits($candidate));

        return $next($selection->prefer('arch', $fits));
    }
}
