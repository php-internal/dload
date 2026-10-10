<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\Repository\Internal\GitHub;

use Internal\DLoad\Module\Config\Schema\Embed\Repository as RepositoryConfig;
use Internal\DLoad\Module\Config\Schema\GitHub;
use Internal\DLoad\Module\HttpClient\Factory as HttpFactory;
use Internal\DLoad\Module\Registry\VersionRegistry;
use Internal\DLoad\Module\Repository\Internal\ApiToken;
use Internal\DLoad\Module\Repository\Internal\GitHub\Api\Client;
use Internal\DLoad\Module\Repository\Internal\GitHub\Api\RepositoryApi;
use Internal\DLoad\Module\Repository\Internal\Server;
use Internal\DLoad\Module\Repository\Internal\ServerTokens;
use Internal\DLoad\Module\Repository\RepositoryFactory;
use Internal\DLoad\Service\Logger;

/**
 * Factory for creating GitHub repository instances.
 *
 * This factory creates instances of {@see GitHubRepository} based on the provided configuration.
 * It checks if the configuration type is 'github' and then initializes the GitHub API client
 * and repository API for the specified organization and repository, on the public GitHub or on
 * the GitHub Enterprise Server set in the `server` attribute.
 *
 * @internal
 * @psalm-internal Internal\DLoad\Module\Repository
 */
final class Factory implements RepositoryFactory
{
    public const PUBLIC_SERVER = 'https://github.com';

    /** @var array<string, Client> Clients by server URL; the public GitHub is under an empty key. */
    private array $clients = [];

    public function __construct(
        private readonly HttpFactory $httpFactory,
        private readonly GitHub $gitHubConfig,
        private readonly Logger $logger,
        private readonly VersionRegistry $registry,
        private readonly ServerTokens $tokens,
    ) {}

    public function supports(RepositoryConfig $config): bool
    {
        return \strtolower($config->type) === 'github';
    }

    public function create(RepositoryConfig $config): GitHubRepository
    {
        $uri = \parse_url($config->uri, PHP_URL_PATH) ?? $config->uri;
        [$org, $repo] = \array_slice(\explode('/', $uri), -2);

        try {
            $server = Server::selfHosted($config->server, self::PUBLIC_SERVER);
        } catch (\InvalidArgumentException $e) {
            throw new \InvalidArgumentException(\sprintf('Repository `%s`: %s', $config->uri, $e->getMessage()), previous: $e);
        }

        $api = new RepositoryApi(
            $this->client($server),
            $this->httpFactory,
            $org,
            $repo,
            $this->logger,
            $server === null ? RepositoryApi::DEFAULT_BASE_URL : $server->url() . '/api/v3',
        );

        return new GitHubRepository($api, $org, $repo, $this->logger, $this->registry, $config->tagPrefix, $server?->authority());
    }

    private function client(?Server $server): Client
    {
        return $this->clients[$server?->url() ?? ''] ??= new Client(
            $this->httpFactory,
            $this->httpFactory->client(),
            $this->token($server),
            $server,
        );
    }

    /**
     * The server's own variable wins over `GITHUB_TOKEN`, which belongs to the public GitHub only.
     */
    private function token(?Server $server): ?ApiToken
    {
        if ($server !== null) {
            return $this->tokens->find($server);
        }

        $token = $this->gitHubConfig->token;

        return $this->tokens->find(Server::fromString(self::PUBLIC_SERVER))
            ?? ($token === null || $token === '' ? null : new ApiToken($token, 'GITHUB_TOKEN'));
    }
}
