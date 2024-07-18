<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\Repository\Internal\GitHub;

use Internal\DLoad\Module\Common\Config\GitHubConfig;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @internal
 * @psalm-internal Internal\DLoad\Module\Repository
 */
final class Factory
{
    public function __construct(
        private readonly GitHubConfig $config,
    ) {}

    /**
     * @param non-empty-string $uri Package name in format "owner/repository" or full URL
     */
    public function create(string $uri): GitHubRepository
    {
        $uri = \parse_url($uri, PHP_URL_PATH) ?? $uri;
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
