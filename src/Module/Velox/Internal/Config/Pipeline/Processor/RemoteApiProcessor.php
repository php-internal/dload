<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\Velox\Internal\Config\Pipeline\Processor;

use Internal\DLoad\Module\Common\FileSystem\Path;
use Internal\DLoad\Module\Velox\ApiClient;
use Internal\DLoad\Module\Velox\Internal\Config\Pipeline\ConfigContext;
use Internal\DLoad\Module\Velox\Internal\Config\Pipeline\ConfigProcessor;
use Internal\DLoad\Module\Velox\Internal\Config\Pipeline\TomlData;

/**
 * Remote API processor (step 1).
 *
 * Adds plugins from remote API when plugins are specified in the action.
 * Runs when $action->plugins is not empty.
 *
 * @internal
 * @psalm-internal Internal\DLoad\Module\Velox
 */
final class RemoteApiProcessor implements ConfigProcessor
{
    public function __construct(
        private readonly ApiClient $apiClient,
    ) {}

    public function __invoke(ConfigContext $context): ConfigContext
    {
        // Early return if no plugins
        if ($context->action->plugins === []) {
            return $context;
        }

        $apiToml = $this->apiClient->generateConfig(
            $context->action->plugins,
            $context->action->golangVersion,
            $context->action->roadrunnerVersion,
        );

        $apiData = TomlData::fromString($apiToml);
        $mergedData = $context->tomlData->merge($apiData);

        return $context->withTomlData($mergedData)
            ->addMetadata('remote_api_applied', true)
            ->addMetadata('plugin_count', \count($context->action->plugins));
    }
}
