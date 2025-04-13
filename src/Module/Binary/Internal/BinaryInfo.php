<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\Binary\Internal;

use Internal\DLoad\Module\Binary\Binary;
use Internal\DLoad\Module\Common\Config\Embed\Binary as BinaryConfig;
use Internal\DLoad\Module\Common\FileSystem\Path;

/**
 * Internal implementation of Binary interface.
 */
final class BinaryInfo implements Binary
{
    private ?string $version = null;

    /**
     * @param non-empty-string $name Binary name
     * @param Path $path Path to binary
     * @param BinaryConfig $config Original configuration
     * @param BinaryExecutor $executor Binary execution service
     * @param VersionResolver $versionResolver Version extraction service
     * @param VersionComparator $versionComparator Version comparison service
     */
    public function __construct(
        private readonly string $name,
        private readonly Path $path,
        private readonly BinaryConfig $config,
        private readonly BinaryExecutor $executor,
        private readonly VersionResolver $versionResolver,
        private readonly VersionComparator $versionComparator,
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

    /**
     * @return non-empty-string|null
     */
    public function getVersion(): ?string
    {
        if ($this->version !== null) {
            return $this->version === '' ? null : $this->version;
        }

        if (!$this->exists() || $this->config->versionCommand === null) {
            return null;
        }

        try {
            $output = $this->executor->execute($this->path, $this->config->versionCommand);
            $this->version = (string) $this->versionResolver->resolveVersion($output);
            return $this->version === '' ? null : $this->version;
        } catch (\Throwable) {
            $this->version = '';
            return null;
        }
    }

    public function satisfiesVersion(string $versionConstraint): ?bool
    {
        $version = $this->getVersion();
        return $version === null
            ? null
            : $this->versionComparator->satisfies($version, $versionConstraint);
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
