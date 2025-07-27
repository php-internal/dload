<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\Velox\Internal\Config;

use Internal\DLoad\Module\Config\Schema\Action\Velox as VeloxAction;

/**
 * Strategy interface for different Velox configuration approaches.
 *
 * @internal
 * @psalm-internal Internal\DLoad\Module\Velox
 */
interface Strategy
{
    /**
     * Determines if this strategy can handle the given action.
     */
    public function supports(VeloxAction $action): bool;

    /**
     * Builds configuration content for the given action.
     *
     * @return string The TOML configuration content
     * @throws \Internal\DLoad\Module\Velox\Exception\Config When configuration cannot be built
     */
    public function build(VeloxAction $action): string;
}
