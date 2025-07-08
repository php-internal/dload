<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\Velox;

use Internal\DLoad\Module\Common\FileSystem\Path;
use Internal\DLoad\Module\Config\Schema\Action\Velox as VeloxAction;
use Internal\DLoad\Module\Downloader\Progress;

/**
 * Builder interface for creating custom software builds.
 *
 * Provides a contract for building software from source with custom configurations.
 * Implementations handle dependency management, configuration generation, and build execution.
 *
 * @internal
 */
interface Builder
{
    /**
     * Builds software according to the provided configuration.
     *
     * Executes the complete build workflow:
     * 1. Downloads and verifies dependencies
     * 2. Generates or processes configuration files
     * 3. Executes the build process
     * 4. Extracts and validates the result
     * 5. Cleans up temporary files
     *
     * @param VeloxAction $config Build configuration
     * @param Path $destination Target directory for the built binary
     * @param \Closure(Progress): mixed $onProgress Progress callback
     * @return Result Build result with binary information
     * @throws Exception\Build When build process fails
     * @throws Exception\Dependency When dependencies cannot be resolved
     * @throws Exception\Config When configuration is invalid
     */
    public function build(VeloxAction $config, Path $destination, \Closure $onProgress): Result;

    /**
     * Validates build configuration without executing the build.
     *
     * Performs preliminary checks:
     * - Configuration syntax and completeness
     * - Dependency availability
     * - Target directory permissions
     *
     * @param VeloxAction $config Configuration to validate
     * @throws Exception\Config When configuration is invalid
     * @throws Exception\Dependency When dependencies are unavailable
     */
    public function validate(VeloxAction $config): void;

    /**
     * Estimates build time based on configuration complexity.
     *
     * @param VeloxAction $config Build configuration
     * @return int Estimated build time in seconds
     */
    public function estimateBuildTime(VeloxAction $config): int;
}
