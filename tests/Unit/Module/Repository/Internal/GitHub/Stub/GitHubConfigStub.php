<?php

declare(strict_types=1);

namespace Internal\DLoad\Tests\Unit\Module\Repository\Internal\GitHub\Stub;

use Internal\DLoad\Module\Config\Schema\GitHub;

/**
 * GitHub configuration factory for testing.
 *
 * Provides helper methods to create GitHub config instances with controlled token values.
 */
final class GitHubConfigStub
{
    public static function withToken(string $token): GitHub
    {
        $config = new GitHub();
        $config->token = $token;
        return $config;
    }

    public static function withoutToken(): GitHub
    {
        $config = new GitHub();
        $config->token = null;
        return $config;
    }
}
