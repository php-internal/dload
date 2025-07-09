<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\Binary\Internal;

use Internal\DLoad\Module\Binary\Binary;
use Internal\DLoad\Module\Binary\BinaryVersion;
use Internal\DLoad\Module\Common\FileSystem\Path;
use Internal\DLoad\Module\Config\Schema\Embed\Binary as BinaryConfig;

/**
 * Internal implementation of Binary interface.
 *
 * @internal
 */
final class LocalBinary implements Binary
{
    private ?BinaryVersion $versionOutput = null;

    /**
     * @param non-empty-string $name Binary name
     * @param Path $path Path to binary
     * @param BinaryConfig $config Original configuration
     * @param BinaryExecutor $executor Binary execution service
     */
    public function __construct(
        private readonly string $name,
        private readonly Path $path,
        private readonly BinaryConfig $config,
        private readonly BinaryExecutor $executor,
    ) {}

    public function getName(): string
    {
        return $this->name;
    }

    public function getPath(): Path
    {
        return $this->path;
    }

    public function exists(): bool
    {
        return $this->path->exists();
    }

    public function getVersion(): ?BinaryVersion
    {
        if ($this->versionOutput !== null) {
            return $this->versionOutput;
        }

        if (!$this->exists() || $this->config->versionCommand === null) {
            return null;
        }

        try {
            $output = $this->executor->execute($this->path, $this->config->versionCommand);
            return $this->versionOutput = BinaryVersion::fromBinaryOutput($output);
        } catch (\Throwable) {
            return $this->versionOutput = BinaryVersion::empty();
        }
    }

    public function getVersionString(): ?string
    {
        return $this->getVersion()?->number;
    }

    public function getSize(): ?int
    {
        if (!$this->exists()) {
            return null;
        }

        $size = \filesize((string) $this->path);
        return $size === false ? null : $size;
    }

    public function getMTime(): ?\DateTimeImmutable
    {
        if (!$this->exists()) {
            return null;
        }

        $mtime = \filemtime((string) $this->path);
        if ($mtime === false) {
            return null;
        }

        try {
            return new \DateTimeImmutable('@' . $mtime);
        } catch (\Exception) {
            return null;
        }
    }
}
