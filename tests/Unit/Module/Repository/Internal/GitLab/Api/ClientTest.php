<?php

declare(strict_types=1);

namespace Internal\DLoad\Tests\Unit\Module\Repository\Internal\GitLab\Api;

use Internal\DLoad\Module\HttpClient\Internal\RedirectingClient;
use Internal\DLoad\Module\Repository\Internal\ApiToken;
use Internal\DLoad\Module\Repository\Internal\GitLab\Api\Client;
use Internal\DLoad\Module\Repository\Internal\Server;
use Internal\DLoad\Tests\Unit\Module\HttpClient\Stub\RedirectServerStub;
use Internal\DLoad\Tests\Unit\Module\Repository\Stub\RecordingHttpFactory;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Test;

#[Covers(Client::class)]
final class ClientTest
{
    #[Test]
    public function publicTokenIsSentToGitLabComOnly(): void
    {
        $http = new RecordingHttpFactory();
        $client = new Client($http, $http, new ApiToken('secret', 'GITLAB_TOKEN'));

        $client->request('GET', 'https://gitlab.com/api/v4/projects/group%2Fproject/releases');
        $client->downloadArtifact('https://gitlab.com/api/v4/projects/group%2Fproject/releases/v1/downloads/a.zip');
        $client->request('GET', 'https://evil.example/api/v4/projects/group%2Fproject/releases');
        $client->downloadArtifact('https://evil.example/gitlab.com/a.zip');
        $client->request('GET', 'http://gitlab.com/api/v4/projects/group%2Fproject/releases');

        Assert::same($http->sent[0]->getHeaderLine('authorization'), 'Bearer secret');
        Assert::same($http->sent[1]->getHeaderLine('private-token'), 'secret');
        Assert::same($http->sent[2]->getHeaderLine('authorization'), '');
        Assert::same($http->sent[3]->getHeaderLine('private-token'), '');
        Assert::same($http->sent[4]->getHeaderLine('authorization'), '');
    }

    #[Test]
    public function selfHostedTokenIsSentToItsServerOnly(): void
    {
        $http = new RecordingHttpFactory();
        $client = new Client(
            $http,
            $http,
            new ApiToken('secret', 'DLOAD_TOKEN_GITLAB_EXAMPLE_COM___8443'),
            Server::fromString('gitlab.example.com:8443'),
        );

        $client->request('GET', 'https://gitlab.example.com:8443/api/v4/projects/group%2Fproject/releases');
        $client->downloadArtifact('https://gitlab.example.com:8443/api/v4/projects/group%2Fproject/releases/v1/downloads/a.zip');
        $client->request('GET', 'https://gitlab.com/api/v4/projects/group%2Fproject/releases');
        $client->downloadArtifact('https://gitlab.example.com/api/v4/projects/group%2Fproject/releases/v1/downloads/a.zip');

        Assert::same($http->sent[0]->getHeaderLine('authorization'), 'Bearer secret');
        Assert::same($http->sent[1]->getHeaderLine('private-token'), 'secret');
        Assert::same($http->sent[2]->getHeaderLine('authorization'), '');
        Assert::same($http->sent[3]->getHeaderLine('private-token'), '');
    }

    #[Test]
    public function tokenIsSentOverPlainHttpOnLoopback(): void
    {
        $http = new RecordingHttpFactory();
        $local = new Client($http, $http, new ApiToken('local', 'DLOAD_TOKEN_127_0_0_1___8080'), Server::fromString('http://127.0.0.1:8080'));
        $remote = new Client($http, $http, new ApiToken('remote', 'DLOAD_TOKEN_GITLAB_EXAMPLE_COM'), Server::fromString('http://gitlab.example.com'));

        $local->request('GET', 'http://127.0.0.1:8080/api/v4/projects/group%2Fproject/releases');
        $remote->request('GET', 'http://gitlab.example.com/api/v4/projects/group%2Fproject/releases');

        Assert::same($http->sent[0]->getHeaderLine('authorization'), 'Bearer local');
        Assert::same($http->sent[1]->getHeaderLine('authorization'), '');
    }

    #[Test]
    public function tokenDoesNotFollowAReleaseLinkToAnotherHost(): void
    {
        $download = 'https://gitlab.example.com/api/v4/projects/group%2Fproject/releases/v1/downloads/a.zip';
        $releases = 'https://gitlab.example.com/api/v4/projects/group%2Fproject/releases';
        $server = new RedirectServerStub([
            $download => [302, 'https://maintainer.example/a.zip'],
            $releases => [302, '/api/v4/projects/7/releases'],
        ]);
        $client = new Client(
            new RecordingHttpFactory(),
            new RedirectingClient($server),
            new ApiToken('secret', 'DLOAD_TOKEN_GITLAB_EXAMPLE_COM'),
            Server::fromString('gitlab.example.com'),
        );

        $client->downloadArtifact($download);
        $client->request('GET', $releases);

        Assert::same($server->sent[0]->getHeaderLine('private-token'), 'secret');
        Assert::same((string) $server->sent[1]->getUri(), 'https://maintainer.example/a.zip');
        Assert::same($server->sent[1]->getHeaderLine('private-token'), '');
        Assert::same((string) $server->sent[3]->getUri(), 'https://gitlab.example.com/api/v4/projects/7/releases');
        Assert::same($server->sent[3]->getHeaderLine('authorization'), 'Bearer secret');
    }
}
