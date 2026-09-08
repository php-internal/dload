<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\Config\Schema;

use Internal\DLoad\Module\Common\Internal\Attribute\Env;
use Internal\DLoad\Module\Common\Internal\Attribute\InflectableConfig;

/**
 * GitHub API configuration.
 *
 * Contains authentication settings for GitHub API access.
 *
 * @internal
 */
#[InflectableConfig]
final class GitHub
{
    /** @var string|null $token API token for GitHub authentication */
    #[Env('GITHUB_TOKEN')]
    public ?string $token = null;
}
