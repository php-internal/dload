<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\Velox\Internal\Config\Pipeline;

use Internal\DLoad\Module\Config\Schema\Action\Velox as VeloxAction;
use Internal\Path;

/**
 * Immutable context for configuration pipeline processing.
 *
 * Carries the action configuration, the build directory and the TOML data built so far.
 *
 * @internal
 * @psalm-internal Internal\DLoad\Module\Velox
 */
final class ConfigContext
{
    /**
     * @param Path $buildDir The directory where the build process will take place
     */
    public function __construct(
        public readonly VeloxAction $action,
        public readonly Path $buildDir,
        public readonly TomlData $tomlData = new TomlData(),
    ) {}

    public function withTomlData(TomlData $tomlData): self
    {
        return new self($this->action, $this->buildDir, $tomlData);
    }
}
