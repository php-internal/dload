<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\Downloader\Internal\AssetSelection\Rule;

use Internal\DLoad\Module\Common\OperatingSystem;
use Internal\DLoad\Module\Downloader\Internal\AssetSelection\AssetRule;
use Internal\DLoad\Module\Downloader\Internal\AssetSelection\Candidate;
use Internal\DLoad\Module\Downloader\Internal\AssetSelection\Selection;

/**
 * Keeps assets built for the host operating system: removes the others in a strict selection,
 * ranks them lower otherwise.
 *
 * @internal
 * @psalm-internal Internal\DLoad\Module\Downloader
 */
final class OperatingSystemRule implements AssetRule
{
    public function __construct(
        private readonly OperatingSystem $operatingSystem,
    ) {}

    public function select(Selection $selection, callable $next): Selection
    {
        $fits = fn(Candidate $candidate): bool => $candidate->asset->getOperatingSystem() === $this->operatingSystem;

        return $next(
            $selection->strict
                ? $selection->remove(static fn(Candidate $candidate): bool => !$fits($candidate))
                : $selection->prefer($fits),
        );
    }
}
