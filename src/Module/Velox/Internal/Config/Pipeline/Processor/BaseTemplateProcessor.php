<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\Velox\Internal\Config\Pipeline\Processor;

use Internal\DLoad\Module\Velox\Internal\Config\Pipeline\ConfigContext;
use Internal\DLoad\Module\Velox\Internal\Config\Pipeline\ConfigProcessor;
use Internal\DLoad\Module\Velox\Internal\Config\Pipeline\TomlData;

/**
 * Base template processor (step 0).
 *
 * Initializes configuration with base template containing
 * essential settings like log configuration and default RoadRunner version.
 * Always runs as the first step in the pipeline.
 *
 * @internal
 * @psalm-internal Internal\DLoad\Module\Velox
 */
final class BaseTemplateProcessor implements ConfigProcessor
{
    public function __invoke(ConfigContext $context): ConfigContext
    {
        $baseTemplate = new TomlData([
            'log' => [
                'level' => 'debug',
                'mode' => 'dev',
            ],
            'debug' => [
                'enabled ' => true,
            ],
        ]);

        return $context->withTomlData($baseTemplate)
            ->addMetadata('base_template_applied', true);
    }
}
