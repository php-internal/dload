<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\Downloader\Internal\AssetSelection\Rule;

use Internal\DLoad\Module\Common\Architecture;
use Internal\DLoad\Module\Common\OperatingSystem;
use Internal\DLoad\Module\Downloader\Internal\AssetSelection\AssetRule;
use Internal\DLoad\Module\Downloader\Internal\AssetSelection\Candidate;
use Internal\DLoad\Module\Downloader\Internal\AssetSelection\Selection;

/**
 * Prefers assets built for the host architecture, then the ones the host emulates.
 *
 * A strict selection removes the assets the host cannot run; otherwise they are ranked last.
 *
 * @internal
 * @psalm-internal Internal\DLoad\Module\Downloader
 */
final class ArchitectureRule implements AssetRule
{
    public function __construct(
        private readonly Architecture $architecture,
        private readonly OperatingSystem $operatingSystem,
    ) {}

    public function select(Selection $selection, callable $next): Selection
    {
        $selection->strict and $selection = $selection->remove(
            fn(Candidate $candidate): bool => $this->rank($candidate->asset->getArchitecture()) === null,
        );

        return $next($selection->rank(
            'arch',
            fn(Candidate $candidate): int => $this->rank($candidate->asset->getArchitecture()) ?? 2,
        ));
    }

    /**
     * @return int<0, 1>|null Null when the host cannot run the asset.
     */
    private function rank(?Architecture $arch): ?int
    {
        return match (true) {
            $arch === $this->architecture => 0,
            // Rosetta 2 on macOS and the built-in emulation of Windows run x86-64 builds on ARM
            $arch === Architecture::X86_64
                && $this->architecture === Architecture::ARM_64
                && \in_array($this->operatingSystem, [OperatingSystem::Darwin, OperatingSystem::Windows], true) => 1,
            default => null,
        };
    }
}
