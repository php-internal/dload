<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\Binary\Internal;

use Internal\DLoad\Module\Binary\Binary;
use Internal\DLoad\Module\Binary\BinaryProvider;
use Internal\DLoad\Module\Common\Config\Embed\Binary as BinaryConfig;
use Internal\DLoad\Module\Common\FileSystem\Path;
use Internal\DLoad\Module\Common\OperatingSystem;

/**
 * Provider implementation for binary instances.
 */
final class BinaryProviderImpl implements BinaryProvider
{
    public function __construct(
        private readonly OperatingSystem $operatingSystem,
        private readonly BinaryExecutor $executor,
        private readonly VersionResolver $versionResolver,
        private readonly VersionComparator $versionComparator,
    ) {}

    public function getBinary(Path|string $destinationPath, BinaryConfig $config): ?Binary
    {
        // Get binary path
        $binaryPath = $this->buildBinaryPath($destinationPath, $config);

        // Create binary instance
        $binary = new BinaryInfo(
            name: $config->name,
            path: $binaryPath,
            config: $config,
            executor: $this->executor,
            versionResolver: $this->versionResolver,
            versionComparator: $this->versionComparator,
        );

        // Return binary only if it exists
        return $binary->exists() ? $binary : null;
    }

    /**
     * Builds the path to a binary without checking if it exists.
     *
     * @param Path|non-empty-string $destinationPath Directory path
     * @param BinaryConfig $config Binary configuration
     * @internal
     */
    private function buildBinaryPath(Path|string $destinationPath, BinaryConfig $config): Path
    {
        return Path::create($destinationPath)
            ->join("{$config->name}{$this->operatingSystem->getBinaryExtension()}");
    }
}
