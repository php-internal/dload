<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\Velox\Internal\Config\Pipeline\Processor;

use Internal\DLoad\Module\Velox\Internal\Config\Pipeline\ConfigContext;
use Internal\DLoad\Module\Velox\Internal\Config\Pipeline\ConfigProcessor;

/**
 * Build mixins processor
 *
 * Applies build action settings such as binary version and Go version.
 * Runs when version settings are provided in the action.
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

        if ($appliedMixins === []) {
            return $context;
        }

        return $context->withTomlData($tomlData)
            ->addMetadata('build_mixins_applied', true)
            ->addMetadata('applied_mixins', $appliedMixins);
    }
}
