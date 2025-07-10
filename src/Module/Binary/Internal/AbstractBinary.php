<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\Binary\Internal;

use Internal\DLoad\Module\Binary\Binary;
use Internal\DLoad\Module\Binary\BinaryVersion;
use Internal\DLoad\Module\Common\FileSystem\Path;
use Internal\DLoad\Module\Config\Schema\Embed\Binary as BinaryConfig;

abstract class AbstractBinary implements Binary
{
    private ?BinaryVersion $versionOutput = null;

    /**
     * @param non-empty-string $name Binary name
     * @param BinaryConfig $config Original configuration
     * @param BinaryExecutor $executor Binary execution service
     */
    public function __construct(
        protected readonly string $name,
        protected readonly BinaryConfig $config,
        protected readonly BinaryExecutor $executor,
    ) {}

    public function getName(): string
    {
        return $this->name;
    }

    public function execute(string ...$args): string
    {
        $args = \array_map(static fn(string $arg): string => \escapeshellarg($arg), $args);

        return $this->executor->execute($this->getPath(), \implode(' ', $args));
    }

    public function exists(): bool
    {
        return $this->getPath()->exists();
    }

    public function getSize(): ?int
    {
        if (!$this->exists()) {
            return null;
        }

        $size = \filesize((string) $this->getPath());
        return $size === false ? null : $size;
    }

    public function getMTime(): ?\DateTimeImmutable
    {
        if (!$this->exists()) {
            return null;
        }

        $mtime = \filemtime((string) $this->getPath());
        if ($mtime === false) {
            return null;
        }

        try {
            return new \DateTimeImmutable('@' . $mtime);
        } catch (\Exception) {
            return null;
        }
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
            $output = $this->executor->execute($this->getPath(), $this->config->versionCommand);
            return $this->versionOutput = BinaryVersion::fromBinaryOutput($output);
        } catch (\Throwable) {
            return $this->versionOutput = BinaryVersion::empty();
        }
    }

    public function getVersionString(): ?string
    {
        return $this->getVersion()?->number;
    }

    abstract public function getPath(): Path;
}
