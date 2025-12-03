<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\Velox\Internal\Config\Pipeline;

use Internal\DLoad\Module\Config\Schema\Action\Velox as VeloxAction;
use Internal\Path;

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
    /**
     * Creates a new immutable configuration context.
     *
     * @param VeloxAction $action The Velox action configuration containing build settings
     * @param Path $buildDir The directory where the build process will take place
     * @param TomlData $tomlData The TOML configuration data being processed through the pipeline
     * @param array<non-empty-string, mixed> $metadata Additional metadata collected during pipeline processing
     */
    public function __construct(
        public readonly VeloxAction $action,
        public readonly Path $buildDir,
        public readonly TomlData $tomlData = new TomlData(),
        public readonly array $metadata = [],
    ) {}

    /**
     * Creates a new context with updated TOML data.
     *
     * This method returns a new immutable instance with the specified TOML data,
     * preserving all other context properties (action, build directory, and metadata).
     *
     * @param TomlData $tomlData The new TOML configuration data
     * @return self A new context instance with the updated TOML data
     */
    public function withTomlData(TomlData $tomlData): self
    {
        return new self($this->action, $this->buildDir, $tomlData, $this->metadata);
    }

    /**
     * Creates a new context with completely replaced metadata.
     *
     * This method returns a new immutable instance with the specified metadata array,
     * completely replacing any existing metadata. To add individual entries while
     * preserving existing metadata, use addMetadata() instead.
     *
     * @param array<non-empty-string, mixed> $metadata The complete metadata array to set
     * @return self A new context instance with the replaced metadata
     */
    public function withMetadata(array $metadata): self
    {
        return new self($this->action, $this->buildDir, $this->tomlData, $metadata);
    }

    /**
     * Adds a single metadata entry to the context.
     *
     * This method returns a new immutable instance with the specified key-value pair
     * added to the metadata, preserving all existing metadata entries. If the key
     * already exists, its value will be overwritten.
     *
     * @param non-empty-string $key The metadata key to add or update
     * @param mixed $value The metadata value to associate with the key
     * @return self A new context instance with the updated metadata
     */
    public function addMetadata(string $key, mixed $value): self
    {
        $metadata = $this->metadata;
        /** @var mixed */
        $metadata[$key] = $value;
        return $this->withMetadata($metadata);
    }
}
