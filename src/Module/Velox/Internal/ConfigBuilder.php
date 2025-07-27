<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\Velox\Internal;

use Internal\DLoad\Module\Common\FileSystem\Path;
use Internal\DLoad\Module\Config\Schema\Action\Velox as VeloxAction;
use Internal\DLoad\Module\Velox\ApiClient;
use Internal\DLoad\Module\Velox\Exception\Config;
use Internal\DLoad\Module\Velox\Exception\Config as ConfigException;
use Internal\DLoad\Module\Velox\Internal\Config\Strategy;
use Internal\DLoad\Module\Velox\Internal\Config\Strategy\Hybrid;
use Internal\DLoad\Module\Velox\Internal\Config\Strategy\Local;
use Internal\DLoad\Module\Velox\Internal\Config\Strategy\Remote;
use Internal\DLoad\Module\Velox\Internal\Config\Validator;
use Internal\DLoad\Service\Logger;

/**
 * Main Velox configuration builder service.
 *
 * Provides functionality to generate velox.toml configuration files
 * from various sources (local files, remote API, or hybrid approach).
 *
 * Uses strategy pattern to handle different configuration scenarios:
 * - Local config file only
 * - Remote API config only
 * - Hybrid (local + remote merge)
 *
 * @internal
 * @psalm-internal Internal\DLoad\Module\Velox
 */
final class ConfigBuilder
{
    public function __construct(
        private readonly ApiClient $apiClient,
        private readonly Validator $validator,
        private readonly Logger $logger,
    ) {}

    /**
     * Builds a velox.toml configuration file for the given action.
     *
     * Handles all configuration scenarios:
     * - Local config file only
     * - Remote API config only
     * - Hybrid (local + remote merge)
     *
     * @param VeloxAction $action Build configuration specification
     * @param Path $buildDir Directory where config file should be created
     * @return Path Path to the generated configuration file
     * @throws Config When configuration cannot be built
     */
    public function buildConfig(VeloxAction $action, Path $buildDir): Path
    {
        $this->logger->debug('Building Velox configuration...');

        $strategy = $this->getConfigStrategy($action);
        $configContent = $strategy->build($action);

        $configPath = $buildDir->join('velox.toml');

        \file_put_contents($configPath->__toString(), $configContent) or throw new ConfigException(
            "Failed to write config file to: {$configPath}",
        );

        $this->logger->debug('Configuration written to: %s', (string) $configPath);

        // Validate the generated configuration
        $this->validateConfig($configPath) or throw new ConfigException(
            "Generated configuration is invalid: {$configPath}",
        );

        return $configPath;
    }

    /**
     * Validates that a configuration file is valid.
     *
     * @param Path $configPath Path to the configuration file to validate
     * @return bool True if configuration is valid, false otherwise
     */
    public function validateConfig(Path $configPath): bool
    {
        return $this->validator->validateTomlFile($configPath);
    }

    /**
     * Selects the appropriate configuration strategy based on action inputs.
     */
    private function getConfigStrategy(VeloxAction $action): Strategy
    {
        return match (true) {
            $action->configFile !== null && $action->plugins !== [] => new Hybrid($this->apiClient),
            $action->configFile !== null => new Local(),
            $action->plugins !== [] => new Remote($this->apiClient),
            default => throw new ConfigException('No valid configuration source provided'),
        };
    }
}
