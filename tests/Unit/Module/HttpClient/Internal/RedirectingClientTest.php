<?php

declare(strict_types=1);

namespace Internal\DLoad\Tests\Unit\Module\HttpClient\Internal;

use Internal\DLoad\Module\HttpClient\Internal\RedirectingClient;
use Internal\DLoad\Tests\Unit\Module\HttpClient\Stub\RedirectServerStub;
use Nyholm\Psr7\Request;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Data\DataProvider;
use Testo\Test;

#[Covers(RedirectingClient::class)]
final class RedirectingClientTest
{
    private const CREDENTIALS = [
        'authorization' => 'Bearer secret',
        'private-token' => 'secret',
        'job-token' => 'secret',
        'cookie' => 'session=secret',
    ];

    public static function provideForeignTargets(): \Generator
    {
        yield 'another host' => ['https://evil.example/a.zip'];
        yield 'a subdomain' => ['https://cdn.gitlab.example.com/a.zip'];
        yield 'another port' => ['https://gitlab.example.com:8443/a.zip'];
        yield 'plain http on the same host' => ['http://gitlab.example.com/a.zip'];
        yield 'scheme-relative to another host' => ['//evil.example/a.zip'];
    }

    public static function provideSameOriginTargets(): \Generator
    {
        yield 'absolute path' => ['/files/a.zip', 'https://gitlab.example.com/files/a.zip'];
        yield 'relative path' => ['files/a.zip', 'https://gitlab.example.com/api/v4/files/a.zip'];
        yield 'dot segments' => ['../../files/./a.zip', 'https://gitlab.example.com/files/a.zip'];
        yield 'explicit default port' => ['https://GitLab.example.com:443/files/a.zip', 'https://gitlab.example.com/files/a.zip'];
    }

    #[DataProvider('provideForeignTargets')]
    #[Test]
    public function credentialsDoNotFollowARedirectToAnotherOrigin(string $location): void
    {
        $server = new RedirectServerStub(['https://gitlab.example.com/api/v4/download' => [302, $location]]);

        $response = (new RedirectingClient($server))->sendRequest(
            new Request('GET', 'https://gitlab.example.com/api/v4/download', self::CREDENTIALS + ['accept' => '*/*']),
        );

        Assert::same($response->getStatusCode(), 200);
        Assert::count($server->sent, 2);
        foreach (\array_keys(self::CREDENTIALS) as $header) {
            Assert::same($server->sent[1]->getHeaderLine($header), '', "`{$header}` must not follow the redirect.");
        }
        Assert::same($server->sent[1]->getHeaderLine('accept'), '*/*');
    }

    #[DataProvider('provideSameOriginTargets')]
    #[Test]
    public function credentialsFollowARedirectWithinTheOrigin(string $location, string $target): void
    {
        $server = new RedirectServerStub(['https://gitlab.example.com/api/v4/download' => [302, $location]]);

        (new RedirectingClient($server))->sendRequest(
            new Request('GET', 'https://gitlab.example.com/api/v4/download', self::CREDENTIALS),
        );

        Assert::same($server->urls()[1], $target);
        Assert::same($server->sent[1]->getHeaderLine('private-token'), 'secret');
        Assert::same($server->sent[1]->getHeaderLine('authorization'), 'Bearer secret');
    }

    #[Test]
    public function credentialsDoNotComeBackAfterLeavingTheOrigin(): void
    {
        $server = new RedirectServerStub([
            'https://gitlab.example.com/a' => [302, 'https://evil.example/b'],
            'https://evil.example/b' => [302, 'https://gitlab.example.com/c'],
        ]);

        (new RedirectingClient($server))->sendRequest(new Request('GET', 'https://gitlab.example.com/a', self::CREDENTIALS));

        Assert::same($server->urls(), ['https://gitlab.example.com/a', 'https://evil.example/b', 'https://gitlab.example.com/c']);
        Assert::same($server->sent[2]->getHeaderLine('private-token'), '');
    }

    #[Test]
    public function hostHeaderFollowsTheTarget(): void
    {
        $server = new RedirectServerStub(['https://gitlab.example.com/a' => [301, 'https://cdn.example/b']]);

        (new RedirectingClient($server))->sendRequest(new Request('GET', 'https://gitlab.example.com/a'));

        Assert::same($server->sent[1]->getHeaderLine('host'), 'cdn.example');
    }

    #[Test]
    public function seeOtherTurnsAPostIntoAGet(): void
    {
        $server = new RedirectServerStub(['https://api.example/build' => [303, '/result']]);

        (new RedirectingClient($server))->sendRequest(
            new Request('POST', 'https://api.example/build', ['content-type' => 'application/json'], '{"a":1}'),
        );

        Assert::same($server->sent[1]->getMethod(), 'GET');
        Assert::same((string) $server->sent[1]->getBody(), '');
        Assert::same($server->sent[1]->getHeaderLine('content-type'), '');
    }

    #[Test]
    public function temporaryRedirectKeepsThePost(): void
    {
        $server = new RedirectServerStub(['https://api.example/build' => [307, '/v2/build']]);

        (new RedirectingClient($server))->sendRequest(new Request('POST', 'https://api.example/build', [], '{"a":1}'));

        Assert::same($server->sent[1]->getMethod(), 'POST');
        Assert::same((string) $server->sent[1]->getBody(), '{"a":1}');
    }

    #[Test]
    public function redirectLoopStopsAtTheLimit(): void
    {
        $server = new RedirectServerStub(['https://api.example/loop' => [302, '/loop']]);

        $response = (new RedirectingClient($server, maxRedirects: 3))->sendRequest(new Request('GET', 'https://api.example/loop'));

        Assert::same($response->getStatusCode(), 302);
        Assert::count($server->sent, 4);
    }

    #[Test]
    public function responseWithoutLocationIsReturnedAsIs(): void
    {
        $server = new RedirectServerStub(['https://api.example/a' => [304, '']]);

        $response = (new RedirectingClient($server))->sendRequest(new Request('GET', 'https://api.example/a'));

        Assert::same($response->getStatusCode(), 304);
        Assert::count($server->sent, 1);
    }
}
