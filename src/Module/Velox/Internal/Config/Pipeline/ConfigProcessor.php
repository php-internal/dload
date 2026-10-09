<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\Velox\Internal\Config\Pipeline;

use Internal\DLoad\Module\Common\Pipeline\Interceptor;

/**
 * Step of the Velox configuration pipeline: applies one configuration source.
 *
 * Steps run in the order set by {@see \Internal\DLoad\Module\Velox\Internal\Config\ConfigPipelineBuilder},
 * and each one overrides the values of the steps before it. A step that has nothing to apply passes
 * the context to `$next` unchanged; a step that does not call `$next` drops all the steps after it.
 *
 * @extends Interceptor<ConfigContext, ConfigContext>
 *
 * @internal
 * @psalm-internal Internal\DLoad\Module\Velox
 */
interface ConfigProcessor extends Interceptor
{
    /**
     * @param callable(ConfigContext): ConfigContext $next
     */
    public function process(ConfigContext $context, callable $next): ConfigContext;
}
