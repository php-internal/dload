<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\Velox\Internal\Config\Pipeline\Processor;

use Internal\DLoad\Module\Velox\Internal\Config\Pipeline\ConfigContext;
use Internal\DLoad\Module\Velox\Internal\Config\Pipeline\ConfigProcessor;
use Internal\Path;

/**
 * Build mixins processor (step 3).
 *
 * Applies build action settings: RoadRunner version, plugin replacements and the debug flag.
 *
 * @internal
 * @psalm-internal Internal\DLoad\Module\Velox
 */
final class BuildMixinsProcessor implements ConfigProcessor
{
    public function process(ConfigContext $context, callable $next): ConfigContext
    {
        $tomlData = $context->tomlData;

        if ($context->action->roadrunnerVersion !== null) {
            $tomlData = $tomlData->set('roadrunner.ref', $context->action->roadrunnerVersion);
        }

        foreach ($context->action->plugins as $plugin) {
            if ($plugin->replace === null) {
                continue;
            }

            $tomlData = $tomlData->set(
                'github.plugins.' . $plugin->name . '.replace',
                \str_starts_with($plugin->replace, 'github.com/')
                    ? $plugin->replace
                    : Path::create($plugin->replace)->absolute()->__toString(),
            );
        }

        $tomlData = $tomlData->set('debug.enabled', $context->action->debug);

        return $next($context->withTomlData($tomlData));
    }
}
