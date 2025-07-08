<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\Velox;

use Internal\DLoad\Module\Config\Schema\Action\Velox\Plugin;

/**
 * Client for interacting with the Velox configuration API.
 *
 * Provides methods to generate velox.toml configurations remotely
 * based on plugin specifications and build requirements.
 *
 * @internal
 */
interface ApiClient
{
    /**
     * Generates a velox.toml configuration from plugin specifications.
     *
     * @param list<Plugin> $plugins List of plugins to include
     * @param string|null $golangVersion Go version constraint
     * @param string|null $binaryVersion RoadRunner binary version
     * @param array<string, mixed> $options Additional configuration options
     * @return string Generated velox.toml content
     * @throws Exception\Api When API request fails
     * @throws Exception\Config When generated config is invalid
     */
    public function generateConfig(
        array $plugins,
        ?string $golangVersion = null,
        ?string $binaryVersion = null,
        array $options = [],
    ): string;

    /**
     * Validates plugin specifications against the API.
     *
     * @param list<Plugin> $plugins Plugins to validate
     * @return array<string, mixed> Validation results
     * @throws Exception\Api When API request fails
     */
    public function validatePlugins(array $plugins): array;

    /**
     * Retrieves available plugin information from the API.
     *
     * @param string|null $search Optional search term
     * @return array<string, mixed> Available plugins
     * @throws Exception\Api When API request fails
     */
    public function getAvailablePlugins(?string $search = null): array;

    /**
     * Checks API availability and health.
     *
     * @return bool True if API is available
     */
    public function isAvailable(): bool;
}
