<?php

declare(strict_types=1);

namespace Internal\DLoad\Tests\Unit\Module\Repository\Internal;

use Internal\DLoad\Module\Repository\Internal\Server;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Data\DataProvider;
use Testo\Expect;
use Testo\Test;

#[Covers(Server::class)]
final class ServerTest
{
    public static function provideValidServers(): \Generator
    {
        yield 'bare host' => ['ghe.example.com', 'https://ghe.example.com', 'ghe.example.com'];
        yield 'host with port' => ['gitlab.example.com:8443', 'https://gitlab.example.com:8443', 'gitlab.example.com:8443'];
        yield 'explicit https' => ['https://ghe.example.com', 'https://ghe.example.com', 'ghe.example.com'];
        yield 'http' => ['http://127.0.0.1:8080', 'http://127.0.0.1:8080', '127.0.0.1:8080'];
        yield 'upper case' => ['HTTPS://GHE.Example.COM', 'https://ghe.example.com', 'ghe.example.com'];
        yield 'trailing slash' => ['https://ghe.example.com/', 'https://ghe.example.com', 'ghe.example.com'];
        yield 'surrounding spaces' => ['  ghe.example.com  ', 'https://ghe.example.com', 'ghe.example.com'];
        yield 'default https port' => ['ghe.example.com:443', 'https://ghe.example.com', 'ghe.example.com'];
        yield 'default http port' => ['http://localhost:80', 'http://localhost', 'localhost'];
        yield 'ipv6' => ['http://[::1]:8080', 'http://[::1]:8080', '[::1]:8080'];
        yield 'punycode' => ['xn--80ak6aa92e.com', 'https://xn--80ak6aa92e.com', 'xn--80ak6aa92e.com'];
    }

    public static function provideInvalidServers(): \Generator
    {
        yield 'empty' => [''];
        yield 'unsupported scheme' => ['ftp://example.com'];
        yield 'path' => ['https://ghe.example.com/api/v3'];
        yield 'query' => ['https://ghe.example.com?a=b'];
        yield 'fragment' => ['https://ghe.example.com#top'];
        yield 'credentials' => ['https://user:pass@ghe.example.com'];
        yield 'malformed host' => ['https://ghe_example.com'];
        yield 'port out of range' => ['ghe.example.com:70000'];
        yield 'empty label' => ['ghe..example.com'];
        yield 'label starting with a hyphen' => ['ghe.-example.com'];
        yield 'label ending with a hyphen' => ['ghe-.example.com'];
        yield 'trailing dot' => ['ghe.example.com.'];
        yield 'malformed ipv6' => ['http://[:::1]'];
    }

    public static function provideTokenVariables(): \Generator
    {
        yield 'public github' => ['github.com', 'DLOAD_TOKEN_GITHUB_COM'];
        yield 'dots' => ['ghe.example.com', 'DLOAD_TOKEN_GHE_EXAMPLE_COM'];
        yield 'hyphen' => ['git-hub.example.com', 'DLOAD_TOKEN_GIT__HUB_EXAMPLE_COM'];
        yield 'double hyphen' => ['xn--80ak6aa92e.com', 'DLOAD_TOKEN_XN____80AK6AA92E_COM'];
        yield 'port' => ['gitlab.example.com:8443', 'DLOAD_TOKEN_GITLAB_EXAMPLE_COM___8443'];
        yield 'default port is not a part' => ['gitlab.example.com:443', 'DLOAD_TOKEN_GITLAB_EXAMPLE_COM'];
        yield 'scheme is not a part' => ['http://127.0.0.1:8080', 'DLOAD_TOKEN_127_0_0_1___8080'];
        yield 'ipv6' => ['http://[::1]:8080', 'DLOAD_TOKEN__0_0_0_0_0_0_0_1___8080'];
        yield 'ipv6 in any spelling' => ['http://[0:0:0:0:0:0:0:1]:8080', 'DLOAD_TOKEN__0_0_0_0_0_0_0_1___8080'];
        yield 'ipv6 with letters' => ['[2001:DB8::a:1]', 'DLOAD_TOKEN__2001_DB8_0_0_0_0_A_1'];
    }

    /**
     * Pairs of distinct servers that a lossy name would merge.
     */
    public static function provideLookalikeServers(): \Generator
    {
        yield 'dot and hyphen' => ['ghe.example.com', 'ghe-example.com'];
        yield 'dot and double hyphen' => ['a.b.c', 'a--b.c'];
        yield 'hyphen and dot in other places' => ['a-b.c', 'a.b-c'];
        yield 'port and label' => ['ghe.example.com:8443', 'ghe.example.com.8443'];
        yield 'port and hyphen' => ['ghe.example.com:8443', 'ghe.example.com-8443'];
        yield 'ipv6 and its last group' => ['[::1]:8080', '1:8080'];
        yield 'ipv6 and a numeric host' => ['[::1]', '0.0.0.0.0.0.0.1'];
    }

    public static function provideSecurity(): \Generator
    {
        yield 'https' => ['https://ghe.example.com', true];
        yield 'http on a remote host' => ['http://ghe.example.com', false];
        yield 'http on localhost' => ['http://localhost:8080', true];
        yield 'http on a localhost subdomain' => ['http://api.localhost', true];
        yield 'http on 127.0.0.1' => ['http://127.0.0.1:8080', true];
        yield 'http on another loopback address' => ['http://127.1.2.3', true];
        yield 'http on ipv6 loopback' => ['http://[::1]', true];
        yield 'http on a lookalike host' => ['http://127.0.0.1.example.com', false];
    }

    public static function provideServedUris(): \Generator
    {
        yield 'same server' => ['https://ghe.example.com', 'https://ghe.example.com/api/v3/repos/o/r', true];
        yield 'explicit default port' => ['https://ghe.example.com', 'https://ghe.example.com:443/o/r', true];
        yield 'host in another case' => ['https://ghe.example.com', 'https://GHE.example.com/o/r', true];
        yield 'same port' => ['ghe.example.com:8443', 'https://ghe.example.com:8443/o/r', true];
        yield 'another port' => ['ghe.example.com:8443', 'https://ghe.example.com/o/r', false];
        yield 'another scheme' => ['https://ghe.example.com', 'http://ghe.example.com/o/r', false];
        yield 'subdomain' => ['https://ghe.example.com', 'https://media.ghe.example.com/o/r', false];
        yield 'host as a path' => ['https://ghe.example.com', 'https://evil.example/ghe.example.com/o/r', false];
        yield 'host as a prefix' => ['https://ghe.example.com', 'https://ghe.example.com.evil.example/o/r', false];
        yield 'not a url' => ['https://ghe.example.com', 'ghe.example.com/o/r', false];
    }

    #[DataProvider('provideValidServers')]
    #[Test]
    public function fromStringNormalizesTheAddress(string $value, string $url, string $authority): void
    {
        $server = Server::fromString($value);

        Assert::same($server->url(), $url);
        Assert::same($server->authority(), $authority);
        Assert::same((string) $server, $url);
    }

    #[DataProvider('provideInvalidServers')]
    #[Test]
    public function fromStringRejectsAnythingButAServerAddress(string $value): never
    {
        Expect::exception(\InvalidArgumentException::class)->withMessageContaining('Invalid server');

        Server::fromString($value);
    }

    #[DataProvider('provideTokenVariables')]
    #[Test]
    public function tokenVariableIsDerivedFromHostAndPort(string $value, string $variable): void
    {
        Assert::same(Server::fromString($value)->tokenVariable(), $variable);
    }

    #[DataProvider('provideLookalikeServers')]
    #[Test]
    public function tokenVariableTellsLookalikeServersApart(string $a, string $b): void
    {
        Assert::notSame(Server::fromString($a)->tokenVariable(), Server::fromString($b)->tokenVariable());
    }

    #[DataProvider('provideSecurity')]
    #[Test]
    public function plainHttpIsSecureOnlyOnLoopback(string $value, bool $secure): void
    {
        Assert::same(Server::fromString($value)->isSecure(), $secure);
    }

    #[DataProvider('provideServedUris')]
    #[Test]
    public function servesMatchesSchemeHostAndPortExactly(string $server, string $uri, bool $served): void
    {
        Assert::same(Server::fromString($server)->serves($uri), $served);
    }
}
