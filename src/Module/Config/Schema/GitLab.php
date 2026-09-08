<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\Config\Schema;

use Internal\DLoad\Module\Common\Internal\Attribute\Env;
use Internal\DLoad\Module\Common\Internal\Attribute\InflectableConfig;

/**
 * GitLab API configuration.
 *
 * Contains authentication settings for GitLab API access.
 *
 * @internal
 */
#[InflectableConfig]
final class GitLab
{
    /** @var string|null $token API token for GitLab authentication */
    #[Env('GITLAB_TOKEN')]
    public ?string $token = null;
}
