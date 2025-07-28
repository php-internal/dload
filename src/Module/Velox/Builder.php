<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\Velox;

use Internal\DLoad\Module\Config\Schema\Action\Velox as VeloxAction;
use Internal\DLoad\Module\Task\Progress;

/**
 * Builder interface for creating custom software builds.
 *
 * Provides a contract for building software from a Velox configuration.
 *
 * @internal
 */
interface Builder
{
    /**
     * Creates a task to build software from the provided configuration.
     *
     * The task executes the complete build workflow:
     * 1. Downloads and verifies dependencies
     * 2. Generates or processes configuration files
     * 3. Executes the build process
     * 4. Extracts and validates the result
     * 5. Cleans up temporary files
     *
     * @param VeloxAction $config Build configuration
     * @param \Closure(Progress): mixed $onProgress Progress callback
     */
    public function build(VeloxAction $config, \Closure $onProgress): Task;
}
