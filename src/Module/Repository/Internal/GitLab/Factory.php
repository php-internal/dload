<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\Repository\Internal\GitLab;

use Internal\DLoad\Module\Config\Schema\Embed\Repository as RepositoryConfig;
use Internal\DLoad\Module\Config\Schema\GitLab;
use Internal\DLoad\Module\HttpClient\Factory as HttpFactory;
use Internal\DLoad\Module\Registry\VersionRegistry;
use Internal\DLoad\Module\Repository\Internal\ApiToken;
use Internal\DLoad\Module\Repository\Internal\GitLab\Api\Client;
use Internal\DLoad\Module\Repository\Internal\GitLab\Api\RepositoryApi;
use Internal\DLoad\Module\Repository\Internal\Server;
use Internal\DLoad\Module\Repository\Internal\ServerTokens;
use Internal\DLoad\Module\Repository\RepositoryFactory;
use Internal\DLoad\Service\Logger;

/**
 * Factory for creating GitLab repository instances.
 *
 * This factory creates instances of {@see GitLabRepository} based on the provided configuration.
 * It checks if the configuration type is 'gitlab' and then initializes the GitLab API client
 * and repository API for the specified project, on the public GitLab or on the self-hosted
 * instance set in the `server` attribute.
 *
 * @internal
 * @psalm-internal Internal\DLoad\Module\Repository
 */
final class Factory implements RepositoryFactory
{
    /** @var array<string, Client> Clients by server URL; the public GitLab is under an empty key. */
    private array $clients = [];

    public function __construct(
        private readonly HttpFactory $httpFactory,
        private readonly GitLab $gitLabConfig,
        private readonly Logger $logger,
        private readonly VersionRegistry $registry,
        private readonly ServerTokens $tokens,
    ) {}

    public function supports(RepositoryConfig $config): bool
    {
        return \strtolower($config->type) === 'gitlab';
    }

    public function create(RepositoryConfig $config): GitLabRepository
    {
        $path = \parse_url($config->uri, PHP_URL_PATH);
        $uri = \is_string($path) && $path !== '' ? $path : $config->uri;

        try {
            $server = Server::selfHosted($config->server, Client::PUBLIC_SERVER);
        } catch (\InvalidArgumentException $e) {
            throw new \InvalidArgumentException(\sprintf('Repository `%s`: %s', $config->uri, $e->getMessage()), previous: $e);
        }

        $api = new RepositoryApi(
            $this->client($server),
            $this->httpFactory,
            $uri,
            $server === null ? RepositoryApi::DEFAULT_BASE_URL : $server->url() . '/api/v4',
        );

        return new GitLabRepository($api, $uri, $this->logger, $this->registry, $config->tagPrefix, $server?->authority());
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
     * The server's own variable wins over `GITLAB_TOKEN`, which belongs to the public GitLab only.
     */
    private function token(?Server $server): ?ApiToken
    {
        if ($server !== null) {
            return $this->tokens->find($server);
        }

        $token = $this->gitLabConfig->token;

        return $this->tokens->find(Server::fromString(Client::PUBLIC_SERVER))
            ?? ($token === null || $token === '' ? null : new ApiToken($token, 'GITLAB_TOKEN'));
    }
}
