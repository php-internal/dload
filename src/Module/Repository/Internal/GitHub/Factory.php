<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\Repository\Internal\GitHub;

use Internal\DLoad\Module\Common\Config\Embed\Repository as RepositoryConfig;
use Internal\DLoad\Module\Common\Config\GitHub as GitHubConfig;
use Internal\DLoad\Module\Repository\RepositoryFactory;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @internal
 * @psalm-internal Internal\DLoad\Module\Repository
 */
final class Factory implements RepositoryFactory
{
    public function __construct(
        private readonly GitHubConfig $config,
    ) {}

    public function supports(RepositoryConfig $config): bool
    {
        return \strtolower($config->type) === 'github';
    }

    public function create(RepositoryConfig $config): GitHubRepository
    {
        $uri = \parse_url($config->uri, PHP_URL_PATH) ?? $config->uri;
        [$org, $repo] = \array_slice(\explode('/', $uri), -2);

        return new GitHubRepository($org, $repo, $this->createClient());
    }

    private function createClient(): HttpClientInterface
    {
        return HttpClient::create([
            'headers' => \array_filter([
                'authorization' => $this->config->token ? 'token ' . $this->config->token : null,
            ]),
        ]);
    }
}
