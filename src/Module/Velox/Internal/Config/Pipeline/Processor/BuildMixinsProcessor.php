<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\Velox\Internal\Config\Pipeline\Processor;

use Internal\DLoad\Module\Common\FileSystem\Path;
use Internal\DLoad\Module\Velox\Internal\Config\Pipeline\ConfigContext;
use Internal\DLoad\Module\Velox\Internal\Config\Pipeline\ConfigProcessor;

/**
 * Build mixins processor
 *
 * Applies build action settings such as RoadRunner version and debug flags.
 *
 * @internal
 * @psalm-internal Internal\DLoad\Module\Velox
 */
final class BuildMixinsProcessor implements ConfigProcessor
{
    public function __invoke(ConfigContext $context): ConfigContext
    {
        $tomlData = $context->tomlData;
        $appliedMixins = [];

        if ($context->action->roadrunnerVersion !== null) {
            $tomlData = $tomlData->set('roadrunner.ref', $context->action->roadrunnerVersion);
            $appliedMixins[] = 'roadrunner_ref';
        }

        // Merge replacements
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
        $appliedMixins[] = 'debug_enabled';
        $tomlData->toToml();

        return $context
            ->withTomlData($tomlData)
            ->addMetadata('build_mixins_applied', true)
            ->addMetadata('applied_mixins', $appliedMixins);
    }
}
