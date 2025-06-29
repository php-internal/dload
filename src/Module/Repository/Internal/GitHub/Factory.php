<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\Repository\Internal\GitHub;

use Internal\DLoad\Module\Config\Schema\Embed\Repository as RepositoryConfig;
use Internal\DLoad\Module\Repository\RepositoryFactory;

/**
 * @internal
 * @psalm-internal Internal\DLoad\Module\Repository
 */
final class Factory implements RepositoryFactory
{
    public function __construct(
        private readonly \Internal\DLoad\Module\HttpClient\Factory $httpFactory,
    ) {}

    public function supports(RepositoryConfig $config): bool
    {
        return \strtolower($config->type) === 'github';
    }

    public function create(RepositoryConfig $config): GitHubRepository
    {
        $uri = \parse_url($config->uri, PHP_URL_PATH) ?? $config->uri;
        [$org, $repo] = \array_slice(\explode('/', $uri), -2);

        return new GitHubRepository($org, $repo, $this->httpFactory, $this->httpFactory->client());
    }
}
