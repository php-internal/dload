<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\Velox\Internal\Config\Pipeline\Processor;

use Internal\DLoad\Module\Velox\Exception\Config as ConfigException;
use Internal\DLoad\Module\Velox\Internal\Config\Pipeline\ConfigContext;
use Internal\DLoad\Module\Velox\Internal\Config\Pipeline\ConfigProcessor;
use Internal\DLoad\Module\Velox\Internal\Config\Pipeline\TomlData;
use Internal\Path;

/**
 * Local file processor (step 2).
 *
 * Merges local velox.toml file content into the configuration.
 * Runs when $action->configFile is not null.
 *
 * @internal
 * @psalm-internal Internal\DLoad\Module\Velox
 */
final class LocalFileProcessor implements ConfigProcessor
{
    public function __invoke(ConfigContext $context): ConfigContext
    {
        // Early return if no config file
        if ($context->action->configFile === null) {
            return $context;
        }

        $configPath = Path::create($context->action->configFile);

        if (!\file_exists($configPath->__toString())) {
            throw new ConfigException(
                "Local config file not found: {$configPath}",
            );
        }

        $localToml = @\file_get_contents($configPath->__toString());

        $localToml === false and throw new ConfigException(
            "Failed to read local config file: {$configPath}.",
        );

        $localData = TomlData::fromString($localToml);
        $mergedData = $context->tomlData->merge($localData);

        return $context->withTomlData($mergedData)
            ->addMetadata('local_file_applied', true)
            ->addMetadata('local_file_path', $configPath->__toString());
    }
}
