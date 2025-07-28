<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\Velox\Internal\Config\Pipeline;

/**
 * Interface for configuration processors in the pipeline.
 *
 * Each processor is invokable and handles a specific configuration source.
 * Processors should check internally if processing is needed and return
 * the context unchanged if not applicable.
 *
 * @internal
 * @psalm-internal Internal\DLoad\Module\Velox
 */
interface ConfigProcessor
{
    /**
     * Processes the configuration context.
     *
     * Should check internally if processing is needed and return
     * the context unchanged if not applicable.
     */
    public function __invoke(ConfigContext $context): ConfigContext;
}
