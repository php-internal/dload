<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\Binary\Internal;

use Internal\DLoad\Module\Common\FileSystem\Path;
use Internal\DLoad\Module\Config\Schema\Embed\Binary as BinaryConfig;

/**
 * Implementation of Binary interface for globally available system binaries.
 *
 * Resolves binary path from system PATH environment variable.
 *
 * @internal
 */
final class GlobalBinary extends AbstractBinary
{
    private ?Path $resolvedPath = null;

    /**
     * @param non-empty-string $name Binary name to resolve from PATH
     * @param BinaryConfig $config Original configuration
     * @param BinaryExecutor $executor Binary execution service
     */
    public function __construct(
        string $name,
        BinaryConfig $config,
        BinaryExecutor $executor,
    ) {
        parent::__construct($name, $config, $executor);
    }

    public function getPath(): Path
    {
        $this->resolvePath();
        return $this->resolvedPath ?? throw new \RuntimeException("Can't resolve path for binary `{$this->name}`");
    }

    /**
     * Resolves the binary path from system PATH environment variable.
     */
    private function resolvePath(): void
    {
        if ($this->resolvedPath !== null) {
            return;
        }

        try {
            $binaryPath = $this->findBinaryInPath($this->name);
            $this->resolvedPath = $binaryPath !== null ? Path::create($binaryPath) : null;
        } catch (\Throwable) {
            $this->resolvedPath = null;
        }
    }

    /**
     * Finds binary executable in system PATH.
     *
     * @param non-empty-string $binaryName Binary name to find
     * @return non-empty-string|null Full path to binary or null if not found
     */
    private function findBinaryInPath(string $binaryName): ?string
    {
        $isWindows = \PHP_OS_FAMILY === 'Windows';
        $command = $isWindows ? 'where' : 'which';

        // Escape binary name for shell execution
        $escapedBinaryName = \escapeshellarg($binaryName);

        // Execute command to find binary
        $output = [];
        $returnCode = 0;

        \exec("$command $escapedBinaryName", $output, $returnCode);

        if ($returnCode !== 0 || $output === []) {
            return null;
        }

        // Return first found path (most relevant)
        $binaryPath = \trim($output[0]);
        return $binaryPath !== '' ? $binaryPath : null;
    }
}
