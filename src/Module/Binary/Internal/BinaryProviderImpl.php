<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\Binary\Internal;

use Internal\DLoad\Module\Binary\Binary;
use Internal\DLoad\Module\Binary\BinaryProvider;
use Internal\DLoad\Module\Common\FileSystem\Path;
use Internal\DLoad\Module\Common\OperatingSystem;
use Internal\DLoad\Module\Config\Schema\Embed\Binary as BinaryConfig;

/**
 * Provider implementation for binary instances.
 *
 * @internal
 */
final class BinaryProviderImpl implements BinaryProvider
{
    public function __construct(
        private readonly OperatingSystem $operatingSystem,
        private readonly BinaryExecutor $executor,
    ) {}

    public function getBinary(Path|string $destinationPath, BinaryConfig $config): ?Binary
    {
        // Get binary path
        $binaryPath = $this->buildBinaryPath($destinationPath, $config);

        // Create binary instance
        $binary = new BinaryHandle(
            name: $config->name,
            path: $binaryPath,
            config: $config,
            executor: $this->executor,
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
