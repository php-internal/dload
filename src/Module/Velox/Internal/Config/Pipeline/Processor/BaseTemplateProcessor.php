<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\Velox\Internal\Config\Pipeline\Processor;

use Internal\DLoad\Module\Velox\Internal\Config\Pipeline\ConfigContext;
use Internal\DLoad\Module\Velox\Internal\Config\Pipeline\ConfigProcessor;
use Internal\DLoad\Module\Velox\Internal\Config\Pipeline\TomlData;

/**
 * Base template processor (step 0).
 *
 * Replaces the configuration with the base template: log settings and the debug flag.
 * Always runs as the first step in the pipeline.
 *
 * @internal
 * @psalm-internal Internal\DLoad\Module\Velox
 */
final class BaseTemplateProcessor implements ConfigProcessor
{
    public function process(ConfigContext $context, callable $next): ConfigContext
    {
        $baseTemplate = new TomlData([
            'log' => [
                'level' => 'debug',
                'mode' => 'dev',
            ],
            'debug' => [
                'enabled' => false,
            ],
        ]);

        return $next($context->withTomlData($baseTemplate));
    }
}
