<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\Velox\Internal\Config\Pipeline;

use Internal\DLoad\Module\Common\FileSystem\Path;
use Internal\DLoad\Module\Config\Schema\Action\Velox as VeloxAction;

/**
 * Immutable context for configuration pipeline processing.
 *
 * Carries state through the pipeline including the action configuration,
 * current TOML data, metadata, and build directory.
 *
 * @internal
 * @psalm-internal Internal\DLoad\Module\Velox
 */
final class ConfigContext
{
    public function __construct(
        public readonly VeloxAction $action,
        public readonly Path $buildDir,
        public readonly TomlData $tomlData = new TomlData(),
        public readonly array $metadata = [],
    ) {}

    public function withTomlData(TomlData $tomlData): self
    {
        return new self($this->action, $this->buildDir, $tomlData, $this->metadata);
    }

    public function withMetadata(array $metadata): self
    {
        return new self($this->action, $this->buildDir, $this->tomlData, $metadata);
    }

    public function addMetadata(string $key, mixed $value): self
    {
        $metadata = $this->metadata;
        $metadata[$key] = $value;
        return $this->withMetadata($metadata);
    }
}
