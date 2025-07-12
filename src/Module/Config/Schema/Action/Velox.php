<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\Config\Schema\Action;

use Internal\DLoad\Module\Common\Internal\Attribute\XPath;
use Internal\DLoad\Module\Common\Internal\Attribute\XPathEmbedList;
use Internal\DLoad\Module\Config\Schema\Action\Velox\Plugin;

/**
 * Velox build action configuration.
 *
 * Represents a velox build action that creates custom RoadRunner binaries
 * with specific plugins using the Velox build tool.
 *
 * Supports three configuration approaches:
 * 1. Local config file only: <velox config-file="./velox.toml" />
 * 2. Remote API config: <velox><plugin name="http"/></velox>
 * 3. Mixed approach: local base + additional plugins via API
 *
 * @internal
 * @link https://docs.roadrunner.dev/docs/customization/build
 */
final class Velox
{
    /** @var non-empty-string|null $veloxVersion Version constraint for velox build tool */
    #[XPath('@velox-version')]
    public ?string $veloxVersion = null;

    /** @var non-empty-string|null $golangVersion Required Go version constraint */
    #[XPath('@golang-version')]
    public ?string $golangVersion = null;

    /** @var non-empty-string|null $binaryVersion RoadRunner version to display in --version */
    #[XPath('@binary-version')]
    public ?string $binaryVersion = null;

    /** @var non-empty-string|null $configFile Path to local velox.toml file */
    #[XPath('@config-file')]
    public ?string $configFile = null;

    /** @var list<Plugin> $plugins List of plugins to include in build */
    #[XPathEmbedList('plugin', Plugin::class)]
    public array $plugins = [];

    /** @var non-empty-string|null $binaryPath Path to the RoadRunner binary to build */
    #[XPath('@binary-path')]
    public ?string $binaryPath = null;
}
