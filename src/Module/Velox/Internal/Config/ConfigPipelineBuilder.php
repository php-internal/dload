<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\Velox\Internal\Config;

use Internal\Container\Container;
use Internal\DLoad\Module\Common\Pipeline\Pipeline;
use Internal\DLoad\Module\Velox\Internal\Config\Pipeline\ConfigContext;
use Internal\DLoad\Module\Velox\Internal\Config\Pipeline\ConfigProcessor;
use Internal\DLoad\Module\Velox\Internal\Config\Pipeline\Processor\BaseTemplateProcessor;
use Internal\DLoad\Module\Velox\Internal\Config\Pipeline\Processor\BuildMixinsProcessor;
use Internal\DLoad\Module\Velox\Internal\Config\Pipeline\Processor\GitHubTokenProcessor;
use Internal\DLoad\Module\Velox\Internal\Config\Pipeline\Processor\LocalFileProcessor;
use Internal\DLoad\Module\Velox\Internal\Config\Pipeline\Processor\RemoteApiProcessor;

/**
 * Builder for configuration processing pipeline.
 *
 * Creates a pipeline with all necessary processors in the correct execution order:
 * 0. BaseTemplate -> 1. RemoteAPI -> 2. LocalFile -> 3. BuildMixins -> 4. GitHubToken
 *
 * @internal
 * @psalm-internal Internal\DLoad\Module\Velox
 */
final class ConfigPipelineBuilder
{
    /**
     * @param list<class-string<ConfigProcessor>> $pipes Processors to be used in the pipeline
     */
    public function __construct(
        private readonly Container $container,
        private readonly array $pipes = [
            BaseTemplateProcessor::class,
            RemoteApiProcessor::class,
            LocalFileProcessor::class,
            BuildMixinsProcessor::class,
            GitHubTokenProcessor::class,
        ],
    ) {}

    /**
     * @see ConfigProcessor::process()
     * @return callable(ConfigContext): ConfigContext
     */
    public function build(): callable
    {
        $processors = \array_map(
            fn(string $pipe): ConfigProcessor => $this->container->get($pipe),
            $this->pipes,
        );

        /** @var callable(ConfigContext): ConfigContext */
        return Pipeline::prepare(...$processors)
            ->with(static fn(ConfigContext $context): ConfigContext => $context, 'process');
    }
}
