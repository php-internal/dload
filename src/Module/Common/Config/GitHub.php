<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\Common\Config;

use Internal\DLoad\Module\Common\Internal\Attribute\Env;

/**
 * @internal
 */
final class GitHub
{
    #[Env('GITHUB_TOKEN')]
    public ?string $token = null;
}
