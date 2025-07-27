<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\Velox\Internal\Config\Strategy;

use Internal\DLoad\Module\Config\Schema\Action\Velox as VeloxAction;
use Internal\DLoad\Module\Velox\ApiClient;
use Internal\DLoad\Module\Velox\Exception\Config as ConfigException;
use Internal\DLoad\Module\Velox\Internal\Config\Strategy;
use Internal\DLoad\Module\Velox\Internal\Config\TomlMerger;

/**
 * Strategy for merging local configuration with remote API-generated plugins.
 *
 * @internal
 * @psalm-internal Internal\DLoad\Module\Velox
 */
final class Hybrid implements Strategy
{
    private readonly Local $localStrategy;
    private readonly Remote $remoteStrategy;

    public function __construct(
        ApiClient $apiClient,
        private readonly TomlMerger $tomlMerger = new TomlMerger(),
    ) {
        $this->localStrategy = new Local();
        $this->remoteStrategy = new Remote($apiClient);
    }

    public function supports(VeloxAction $action): bool
    {
        return $action->configFile !== null && $action->plugins !== [];
    }

    public function build(VeloxAction $action): string
    {
        $this->supports($action) or throw new ConfigException(
            'Hybrid config strategy requires both config file and plugins',
        );

        // Get local config content
        $localConfig = $this->localStrategy->build($action);

        // Generate remote config from plugins
        $remoteConfig = $this->remoteStrategy->build($action);

        // Merge configurations (remote plugins extend/override local)
        return $this->tomlMerger->merge($localConfig, $remoteConfig);
    }
}
