<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\Velox\Internal\Config\Pipeline\Processor;

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

    public function process(ConfigContext $context, callable $next): ConfigContext
    {
        if ($context->action->plugins === []) {
            return $next($context);
        }

        $apiToml = $this->apiClient->generateConfig(
            $context->action->plugins,
            $context->action->golangVersion,
            $context->action->roadrunnerVersion,
        );

        $apiData = TomlData::fromString($apiToml);
        $mergedData = $context->tomlData->merge($apiData);

        return $next(
            $context->withTomlData($mergedData)
                ->addMetadata('remote_api_applied', true)
                ->addMetadata('plugin_count', \count($context->action->plugins)),
        );
    }
}
