<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\Config\Schema\Action\Velox;

use Internal\DLoad\Module\Common\Internal\Attribute\XPath;

/**
 * Velox plugin configuration.
 *
 * Represents a single plugin to be included in the RoadRunner build.
 * Plugins can be specified with various levels of detail:
 * - Minimal: <plugin name="http" />
 * - With version: <plugin name="temporal" version="^4.2.1" />
 * - Full specification: <plugin name="custom" version="^1.0" owner="my-org" repository="rr-custom" />
 *
 * @internal
 */
final class Plugin
{
    /** @var non-empty-string $name Plugin name (required) */
    #[XPath('@name')]
    public string $name;

    /** @var non-empty-string|null $version Plugin version constraint */
    #[XPath('@version')]
    public ?string $version = null;

    /** @var non-empty-string|null $owner Repository owner/organization */
    #[XPath('@owner')]
    public ?string $owner = null;

    /** @var non-empty-string|null $repository Repository name */
    #[XPath('@repository')]
    public ?string $repository = null;
}
