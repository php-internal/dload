<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\Velox\Internal\Config\Strategy;

use Internal\DLoad\Module\Config\Schema\Action\Velox as VeloxAction;
use Internal\DLoad\Module\Velox\ApiClient;
use Internal\DLoad\Module\Velox\Exception\Config as ConfigException;
use Internal\DLoad\Module\Velox\Internal\Config\Strategy;

/**
 * Strategy for generating Velox configuration via remote API.
 *
 * @internal
 * @psalm-internal Internal\DLoad\Module\Velox
 */
final class Remote implements Strategy
{
    public function __construct(
        private readonly ApiClient $apiClient,
    ) {}

    public function supports(VeloxAction $action): bool
    {
        return $action->configFile === null && $action->plugins !== [];
    }

    public function build(VeloxAction $action): string
    {
        $action->plugins !== [] or throw new ConfigException(
            'Remote config strategy requires at least one plugin',
        );

        return $this->apiClient->generateConfig(
            plugins: $action->plugins,
            golangVersion: $action->golangVersion,
            binaryVersion: $action->binaryVersion,
            options: $action->options ?? [],
        );
    }
}
