<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\Velox\Internal\Config;

use Internal\DLoad\Module\Config\Schema\Action\Velox as VeloxAction;
use Internal\DLoad\Module\Velox\Exception\Config as ConfigException;
use Internal\Path;

/**
 * Configuration validator for Velox actions.
 *
 * @internal
 */
final class Validator
{
    /**
     * Validates a Velox action configuration.
     *
     * @param VeloxAction $config Configuration to validate
     * @throws ConfigException When configuration is invalid
     */
    public static function validate(VeloxAction $config): void
    {
        self::validateConfigSource($config);
        self::validateLocalConfigFile($config);
    }

    /**
     * Validates a TOML configuration file.
     *
     * @param Path $configPath Path to the TOML file
     * @return bool True if valid, false otherwise
     */
    public function validateTomlFile(Path $configPath): bool
    {
        if (!$configPath->exists() || !$configPath->isFile()) {
            return false;
        }

        $content = \file_get_contents($configPath->__toString());
        if ($content === false) {
            return false;
        }

        // Basic TOML validation - check for required sections
        return $this->hasValidTomlStructure($content);
    }

    private static function validateConfigSource(VeloxAction $config): void
    {
        if ($config->configFile === null && $config->plugins === []) {
            throw new ConfigException(
                'Velox configuration must specify either config-file or plugins list',
            );
        }
    }

    private static function validateLocalConfigFile(VeloxAction $config): void
    {
        if ($config->configFile === null) {
            return;
        }

        $configPath = Path::create($config->configFile);

        if (!$configPath->exists()) {
            throw new ConfigException(
                "Velox config file not found: {$config->configFile}",
                configPath: $config->configFile,
            );
        }

        if (!$configPath->isFile()) {
            throw new ConfigException(
                "Velox config path is not a file: {$config->configFile}",
                configPath: $config->configFile,
            );
        }
    }

    /**
     * Validates basic TOML structure for Velox configurations.
     */
    private function hasValidTomlStructure(string $content): bool
    {
        // Check for basic TOML syntax errors
        $lines = \explode("\n", $content);

        foreach ($lines as $line) {
            $line = \trim($line);

            // Skip empty lines and comments
            if ($line === '' || \str_starts_with($line, '#')) {
                continue;
            }

            // Check section headers
            if (\str_starts_with($line, '[') && \str_ends_with($line, ']')) {
                continue;
            }

            // Check key-value pairs
            if (\str_contains($line, '=')) {
                continue;
            }

            // Invalid line found
            return false;
        }

        return true;
    }
}
