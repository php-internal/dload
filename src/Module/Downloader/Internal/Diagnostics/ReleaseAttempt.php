<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\Downloader\Internal\Diagnostics;

/**
 * Collected information about an attempt to download a specific release.
 *
 * @internal
 */
final class ReleaseAttempt
{
    /** Maximum number of asset names listed in the report. */
    private const ASSETS_LIMIT = 15;

    /** @var int<0, max> Total number of assets in the release */
    public int $assetsTotal = 0;

    /** @var string|null Why the release was rejected */
    public ?string $reason = null;

    /** @var list<string> Names of all assets in the release */
    private array $assetNames = [];

    /** @var list<non-empty-string> Errors occurred while downloading matched assets */
    private array $failures = [];

    /**
     * @param non-empty-string $name Release name or tag
     */
    public function __construct(
        public readonly string $name,
    ) {}

    /**
     * @param list<string> $names Names of all assets available in the release
     */
    public function registerAssets(array $names): void
    {
        $this->assetsTotal = \count($names);
        $this->assetNames = $names;
    }

    /**
     * Registers a failure of a matched asset, e.g. a broken download.
     *
     * @param non-empty-string $assetName
     */
    public function addFailure(string $assetName, \Throwable $error): void
    {
        $this->failures[] = \sprintf('`%s`: %s', $assetName, $error->getMessage());
    }

    /**
     * Renders release details as report lines.
     *
     * @return list<string>
     */
    public function describe(): array
    {
        $lines = [
            \sprintf(
                '%s: %d asset(s)%s',
                $this->name,
                $this->assetsTotal,
                $this->reason === null ? '' : ', ' . $this->reason,
            ),
        ];

        if ($this->assetNames !== []) {
            $listed = \array_slice($this->assetNames, 0, self::ASSETS_LIMIT);
            $hidden = \count($this->assetNames) - \count($listed);
            $lines[] = \sprintf(
                '  Assets: %s%s',
                \implode(', ', $listed),
                $hidden > 0 ? \sprintf(' and %d more', $hidden) : '',
            );
        }

        foreach ($this->failures as $failure) {
            $lines[] = '  Failed asset ' . $failure;
        }

        return $lines;
    }
}
