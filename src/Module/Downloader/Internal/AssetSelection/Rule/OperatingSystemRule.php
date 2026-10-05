<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\Downloader\Internal\AssetSelection\Rule;

use Internal\DLoad\Module\Common\OperatingSystem;
use Internal\DLoad\Module\Downloader\Internal\AssetSelection\AssetRule;
use Internal\DLoad\Module\Downloader\Internal\AssetSelection\Candidate;
use Internal\DLoad\Module\Downloader\Internal\AssetSelection\Selection;

/**
 * Prefers assets built for the host operating system, then the ones it can run.
 *
 * A strict selection removes the assets the host cannot run; otherwise they are ranked last.
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
        $selection->strict and $selection = $selection->remove(
            fn(Candidate $candidate): bool => $this->rank($candidate->asset->getOperatingSystem()) === null,
        );

        return $next($selection->rank(
            'os',
            fn(Candidate $candidate): int => $this->rank($candidate->asset->getOperatingSystem()) ?? 2,
        ));
    }

    /**
     * @return int<0, 1>|null Null when the host cannot run the asset.
     */
    private function rank(?OperatingSystem $os): ?int
    {
        return match (true) {
            $os === $this->operatingSystem => 0,
            // Static Linux binaries run on Android, while Android builds need its runtime
            $os === OperatingSystem::Linux && $this->operatingSystem === OperatingSystem::Android => 1,
            default => null,
        };
    }
}
