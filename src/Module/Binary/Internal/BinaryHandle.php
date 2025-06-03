<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\Binary\Internal;

use Composer\Semver\Semver;
use Internal\DLoad\Module\Binary\Binary;
use Internal\DLoad\Module\Binary\Version;
use Internal\DLoad\Module\Common\Config\Embed\Binary as BinaryConfig;
use Internal\DLoad\Module\Common\FileSystem\Path;
use Internal\DLoad\Module\Common\Stability;
use Internal\DLoad\Module\Common\VersionConstraint;

/**
 * Internal implementation of Binary interface.
 *
 * @internal
 */
final class BinaryHandle implements Binary
{
    private ?Version $versionOutput = null;

    /**
     * @param non-empty-string $name Binary name
     * @param Path $path Path to binary
     * @param BinaryConfig $config Original configuration
     * @param BinaryExecutor $executor Binary execution service
     * @param VersionResolver $versionResolver Version extraction service
     */
    public function __construct(
        private readonly string $name,
        private readonly Path $path,
        private readonly BinaryConfig $config,
        private readonly BinaryExecutor $executor,
        private readonly VersionResolver $versionResolver,
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

    public function getVersion(): ?Version
    {
        if ($this->versionOutput !== null) {
            return $this->versionOutput;
        }

        if (!$this->exists() || $this->config->versionCommand === null) {
            return null;
        }

        try {
            $output = $this->executor->execute($this->path, $this->config->versionCommand);
            return $this->versionOutput = $this->versionResolver->resolveVersion($output);
        } catch (\Throwable) {
            return $this->versionOutput = Version::empty();
        }
    }

    public function getVersionString(): ?string
    {
        return $this->getVersion()?->version;
    }

    public function satisfiesVersion(VersionConstraint $versionConstraint): ?bool
    {
        $version = $this->getVersion();
        $versionString = $version?->version;
        if ($versionString === null) {
            return null;
        }

        // Check if a version satisfies the base version constraint
        if (Semver::satisfies($versionString, $versionConstraint->versionConstraint) === false) {
            return false;
        }

        // Check if the binary version satisfies the feature suffix constraint
        if ($versionConstraint->featureSuffix !== null) {
            if (!\str_contains($versionString, $versionConstraint->featureSuffix)) {
                return false;
            }
        }

        // Check if the version satisfies the stability constraint
        $stability = $version->stability ?? Stability::Stable;
        return $stability->meetsMinimum($versionConstraint->minimumStability);
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
