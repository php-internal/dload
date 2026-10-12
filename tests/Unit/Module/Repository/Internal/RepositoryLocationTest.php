<?php

declare(strict_types=1);

namespace Internal\DLoad\Tests\Unit\Module\Repository\Internal;

use Internal\DLoad\Module\Repository\Internal\RepositoryLocation;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Data\DataProvider;
use Testo\Expect;
use Testo\Test;

#[Covers(RepositoryLocation::class)]
final class RepositoryLocationTest
{
    private const PUBLIC = 'https://github.com';

    public static function provideLocations(): \Generator
    {
        yield 'path' => ['owner/repo', null, 'owner/repo'];
        yield 'path with surrounding slashes and spaces' => [' /owner/repo/ ', null, 'owner/repo'];
        yield 'nested path' => ['group/sub/project', null, 'group/sub/project'];
        yield 'public url' => ['https://github.com/owner/repo', null, 'owner/repo'];
        yield 'public url in upper case' => ['HTTPS://GitHub.COM/owner/repo', null, 'owner/repo'];
        yield 'public url over http' => ['http://github.com/owner/repo', null, 'owner/repo'];
        yield 'public url with the default port' => ['https://github.com:443/owner/repo', null, 'owner/repo'];
        yield 'public host without a scheme' => ['github.com/owner/repo', null, 'owner/repo'];
        yield 'clone url' => ['https://github.com/owner/repo.git', null, 'owner/repo'];
        yield 'self-hosted' => ['https://ghe.example.com/owner/repo', 'https://ghe.example.com', 'owner/repo'];
        yield 'self-hosted with a port' => [
            'https://gitlab.example.com:8443/group/sub/project/',
            'https://gitlab.example.com:8443',
            'group/sub/project',
        ];
        yield 'public host on another port' => ['https://github.com:8443/owner/repo', 'https://github.com:8443', 'owner/repo'];
        yield 'subdomain of the public host' => ['https://www.github.com/owner/repo', 'https://www.github.com', 'owner/repo'];
        yield 'loopback over http' => ['http://127.0.0.1:8080/owner/repo', 'http://127.0.0.1:8080', 'owner/repo'];
        yield 'ipv6' => ['http://[::1]:8080/owner/repo', 'http://[::1]:8080', 'owner/repo'];
        yield 'host without a scheme stays a path' => ['ghe.example.com/owner/repo', null, 'ghe.example.com/owner/repo'];
    }

    public static function provideInvalidUris(): \Generator
    {
        yield 'only slashes' => ['/', 'no repository path'];
        yield 'server without a path' => ['https://ghe.example.com', 'no repository path'];
        yield 'unsupported scheme' => ['ftp://ghe.example.com/owner/repo', 'invalid server'];
        yield 'credentials' => ['https://token@ghe.example.com/owner/repo', 'credentials are not allowed'];
        yield 'no host' => ['https:///owner/repo', 'invalid server'];
        yield 'query' => ['https://ghe.example.com/owner/repo?tab=readme', 'a query or fragment'];
        yield 'fragment in a path' => ['owner/repo#readme', 'a query or fragment'];
        yield 'page inside a project' => ['https://gitlab.example.com/group/project/-/releases', 'points inside a repository'];
        yield 'empty segment' => ['owner//repo', 'malformed'];
        yield 'dot segment' => ['owner/./repo', 'malformed'];
        yield 'parent segment' => ['https://ghe.example.com/owner/../repo', 'malformed'];
        yield 'nothing but .git' => ['owner/.git', 'malformed'];
    }

    #[DataProvider('provideLocations')]
    #[Test]
    public function splitsTheUriIntoServerAndPath(string $uri, ?string $server, string $path): void
    {
        $location = RepositoryLocation::fromUri($uri, self::PUBLIC);

        Assert::same($location->server?->url(), $server);
        Assert::same($location->path(), $path);
    }

    #[DataProvider('provideInvalidUris')]
    #[Test]
    public function rejectsAnythingButARepositoryPathOrUrl(string $uri, string $reason): never
    {
        Expect::exception(\InvalidArgumentException::class)->withMessagePattern(
            \sprintf('/^Repository `%s`: .*%s/', \preg_quote(\trim($uri), '/'), \preg_quote($reason, '/')),
        );

        RepositoryLocation::fromUri($uri, self::PUBLIC);
    }

    #[Test]
    public function invalidNamesTheRepository(): void
    {
        $error = RepositoryLocation::fromUri('owner/repo', self::PUBLIC)->invalid('expected something else.');

        Assert::same($error->getMessage(), 'Repository `owner/repo`: expected something else.');
    }
}
