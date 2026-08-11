<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\Downloader\Internal\Diagnostics;

use Internal\DLoad\Module\Common\Architecture;
use Internal\DLoad\Module\Common\OperatingSystem;
use Internal\DLoad\Module\Common\Stability;
use Internal\DLoad\Module\Config\Schema\Action\Download as DownloadConfig;
use Internal\DLoad\Module\Config\Schema\Embed\Software;

/**
 * Collects the reason of every failed download attempt to build a single readable report.
 *
 * Without such a report a failed download only says "nothing found", while the actual reason
 * (an API error, a version constraint that matches nothing, assets for other platforms, etc.)
 * stays hidden.
 *
 * ```php
 * $diagnostics = new DownloadDiagnostics($software, $config, $os, $arch, $stability);
 * $repository = $diagnostics->addRepository('github', 'owner/repo', '/^.*$/');
 * $repository->matchedReleases = 0;
 * throw new DownloadFailed($diagnostics->render());
 * ```
 *
 * @internal
 */
final class DownloadDiagnostics
{
    /** @var list<RepositoryAttempt> */
    private array $repositories = [];

    public function __construct(
        private readonly Software $software,
        private readonly DownloadConfig $actionConfig,
        private readonly OperatingSystem $operatingSystem,
        private readonly Architecture $architecture,
        private readonly Stability $stability,
    ) {}

    /**
     * @param non-empty-string $type Repository type, e.g. `github`
     * @param non-empty-string $name Repository name, e.g. `owner/repo`
     * @param non-empty-string $assetPattern Asset name pattern from the configuration
     */
    public function addRepository(string $type, string $name, string $assetPattern): RepositoryAttempt
    {
        return $this->repositories[] = new RepositoryAttempt($type, $name, $assetPattern);
    }

    /**
     * Builds the human-readable report about all the attempts.
     *
     * @return non-empty-string
     */
    public function render(): string
    {
        $lines = [$this->renderRequest()];

        if ($this->repositories === []) {
            $lines[] = \sprintf(
                'No repositories are configured for `%s`. Add a `repository` entry to the software definition.',
                $this->software->getId(),
            );

            return \implode("\n", $lines);
        }

        $lines[] = \sprintf('Tried %d repository(ies):', \count($this->repositories));

        foreach ($this->repositories as $index => $repository) {
            foreach ($repository->describe() as $lineIndex => $line) {
                $lines[] = $lineIndex === 0
                    ? \sprintf('  %d) %s', $index + 1, $line)
                    : '  ' . $line;
            }
        }

        return \implode("\n", $lines);
    }

    /**
     * @return non-empty-string
     */
    private function renderRequest(): string
    {
        return \sprintf(
            'Requested: version `%s`, OS `%s`, architecture `%s`, minimum stability `%s`, asset type `%s`.',
            $this->actionConfig->version ?? 'any',
            $this->operatingSystem->value,
            $this->architecture->value,
            $this->stability->value,
            $this->actionConfig->type?->value ?? 'any',
        );
    }
}
