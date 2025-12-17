<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\Config\Schema;

use Internal\DLoad\Module\Common\Internal\Attribute\Env;

/**
 * GitLab API configuration.
 *
 * Contains authentication settings for GitLab API access.
 *
 * @internal
 */
final class GitLab
{
    /** @var string|null $token API token for GitLab authentication */
    #[Env('GITLAB_TOKEN')]
    public ?string $token = null;
}
