<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\Velox\Internal;

use Internal\DLoad\Module\Config\Schema\Action\Velox as VeloxAction;
use Internal\DLoad\Module\Velox\Exception\Config;
use Internal\DLoad\Module\Velox\Exception\Config as ConfigException;
use Internal\DLoad\Module\Velox\Internal\Config\ConfigPipelineBuilder;
use Internal\DLoad\Module\Velox\Internal\Config\Pipeline\ConfigContext;
use Internal\DLoad\Module\Velox\Internal\Config\Validator;
use Internal\DLoad\Service\Logger;
use Internal\Path;

/**
 * Main Velox configuration builder service.
 *
 * Provides functionality to generate velox.toml configuration files
 * using a pipeline-based architecture that processes multiple configuration
 * sources in sequence: base template → remote API → local file → build mixins → GitHub token.
 *
 * @internal
 * @psalm-internal Internal\DLoad\Module\Velox
 */
final class ConfigBuilder
{
    public function __construct(
        private readonly ConfigPipelineBuilder $pipelineBuilder,
        private readonly Validator $validator,
        private readonly Logger $logger,
    ) {}

    /**
     * Builds a velox.toml configuration file for the given action.
     *
     * Uses pipeline-based architecture to process configuration sources in sequence:
     * 0. Base template → 1. Remote API → 2. Local file → 3. Build mixins → 4. GitHub token
     *
     * @param VeloxAction $action Build configuration specification
     * @param Path $buildDir Directory where config file should be created
     * @return Path Path to the generated configuration file
     * @throws Config When configuration cannot be built
     */
    public function buildConfig(VeloxAction $action, Path $buildDir): Path
    {
        $this->logger->debug('Building Velox configuration with pipeline...');

        $pipeline = $this->pipelineBuilder->build();
        $context = new ConfigContext($action, $buildDir);

        $result = $pipeline->process($context);
        $configContent = $result->tomlData->toToml();

        $configPath = $buildDir->join('velox.toml');

        \file_put_contents($configPath->__toString(), $configContent) === false and throw new ConfigException(
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
}
