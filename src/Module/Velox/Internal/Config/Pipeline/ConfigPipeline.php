<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\Velox\Internal\Config\Pipeline;

/**
 * Configuration pipeline that processes multiple sources sequentially.
 *
 * Uses array_reduce with invokable processors for clean functional composition.
 * Executes processors in the order they are provided, passing the context
 * through each processor in sequence.
 *
 * @internal
 * @psalm-internal Internal\DLoad\Module\Velox
 */
final class ConfigPipeline
{
    /**
     * @param list<ConfigProcessor> $processors
     */
    public function __construct(
        private readonly array $processors,
    ) {}

    public function process(ConfigContext $context): ConfigContext
    {
        return \array_reduce(
            $this->processors,
            static fn(ConfigContext $ctx, ConfigProcessor $processor): ConfigContext => $processor($ctx),
            $context,
        );
    }
}
