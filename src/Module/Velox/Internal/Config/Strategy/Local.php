<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\Velox\Internal\Config\Strategy;

use Internal\DLoad\Module\Common\FileSystem\Path;
use Internal\DLoad\Module\Config\Schema\Action\Velox as VeloxAction;
use Internal\DLoad\Module\Velox\Exception\Config as ConfigException;
use Internal\DLoad\Module\Velox\Internal\Config\Strategy;

/**
 * Strategy for handling local Velox configuration files.
 *
 * @internal
 * @psalm-internal Internal\DLoad\Module\Velox
 */
final class Local implements Strategy
{
    public function supports(VeloxAction $action): bool
    {
        return $action->configFile !== null && $action->plugins === [];
    }

    public function build(VeloxAction $action): string
    {
        $configFile = $action->configFile ?? throw new ConfigException(
            'Local config strategy requires a config file',
        );

        $configPath = Path::create($configFile);

        $configPath->exists() or throw new ConfigException(
            "Config file not found: {$configFile}",
            configPath: $configFile,
        );

        $configPath->isFile() or throw new ConfigException(
            "Config path is not a file: {$configFile}",
            configPath: $configFile,
        );

        $configPath->isReadable() or throw new ConfigException(
            "Config file is not readable: {$configFile}",
            configPath: $configFile,
        );

        $content = \file_get_contents($configPath->__toString());

        $content !== false or throw new ConfigException(
            "Failed to read config file: {$configFile}",
            configPath: $configFile,
        );

        return $content;
    }
}
