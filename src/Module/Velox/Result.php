<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\Velox;

use Internal\DLoad\Module\Common\FileSystem\Path;
use Internal\DLoad\Module\Version\Version;

/**
 * Represents the result of a successful build operation.
 *
 * Contains information about the built binary, build metadata,
 * and any additional artifacts produced during the build process.
 *
 * @internal
 */
final class Result
{
    /**
     * Creates a new build result.
     *
     * @param Path $binaryPath Path to the built binary
     * @param Version $version Version of the built software
     * @param array<string, mixed> $metadata Additional build metadata
     * @param int $buildDuration Build time in seconds
     * @param list<Path> $artifacts Additional artifacts created during build
     */
    public function __construct(
        public readonly Path $binaryPath,
        public readonly Version $version,
        public readonly array $metadata = [],
        public readonly int $buildDuration = 0,
        public readonly array $artifacts = [],
    ) {}

    /**
     * Checks if the built binary exists and is executable.
     *
     * @return bool True if binary is valid and executable
     */
    public function isValid(): bool
    {
        return $this->binaryPath->exists()
            && $this->binaryPath->isFile()
            && \is_executable((string) $this->binaryPath);
    }

    /**
     * Returns the size of the built binary in bytes.
     *
     * @return int|null Binary size or null if file doesn't exist
     */
    public function getBinarySize(): ?int
    {
        if (!$this->binaryPath->exists()) {
            return null;
        }
        return \filesize((string) $this->binaryPath) ?: null;
    }

    /**
     * Returns build metadata as a formatted string.
     *
     * @return string Human-readable build information
     */
    public function getSummary(): string
    {
        $size = $this->getBinarySize();
        $sizeStr = $size !== null ? \sprintf('%.2f MB', $size / 1024 / 1024) : 'unknown';

        return \sprintf(
            'Built %s v%s (%s, %ds)',
            $this->binaryPath->name(),
            $this->version,
            $sizeStr,
            $this->buildDuration,
        );
    }
}
