<?php

declare(strict_types=1);

namespace Internal\DLoad\Tests\Unit\Module\Repository\Internal\GitHub;

use Internal\DLoad\Module\Config\Schema\Embed\Repository as RepositoryConfig;
use Internal\DLoad\Module\Config\Schema\GitHub as GitHubConfig;
use Internal\DLoad\Module\Registry\Internal\PassThroughRegistry;
use Internal\DLoad\Module\Repository\Internal\GitHub\Factory;
use Internal\DLoad\Module\Repository\Internal\ServerTokens;
use Internal\DLoad\Module\Repository\Repository;
use Internal\DLoad\Service\Logger;
use Internal\DLoad\Tests\Unit\Module\Repository\Stub\RecordingHttpFactory;
use Psr\Http\Message\RequestInterface;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Data\DataProvider;
use Testo\Expect;
use Testo\Test;

#[Covers(Factory::class)]
final class FactoryTest
{
    private RecordingHttpFactory $http;

    public static function provideTokenSources(): \Generator
    {
        yield 'no token' => [null, [], null, ''];
        yield 'GITHUB_TOKEN on the public host' => [null, [], 'public', 'Bearer public'];
        yield 'server variable wins over GITHUB_TOKEN' => [
            null,
            ['DLOAD_TOKEN_GITHUB_COM' => 'own'],
            'public',
            'Bearer own',
        ];
        yield 'empty server variable is ignored' => [null, ['DLOAD_TOKEN_GITHUB_COM' => ''], 'public', 'Bearer public'];
        yield 'GITHUB_TOKEN never leaves the public host' => ['ghe.example.com', [], 'public', ''];
        yield 'custom server variable' => [
            'ghe.example.com',
            ['DLOAD_TOKEN_GHE_EXAMPLE_COM' => 'ghe'],
            'public',
            'Bearer ghe',
        ];
        yield 'variable of another server' => [
            'ghe.example.com',
            ['DLOAD_TOKEN_GITHUB_COM' => 'own', 'DLOAD_TOKEN_OTHER_EXAMPLE_COM' => 'other'],
            null,
            '',
        ];
        yield 'no token over plain http' => [
            'http://ghe.example.com',
            ['DLOAD_TOKEN_GHE_EXAMPLE_COM' => 'ghe'],
            null,
            '',
        ];
        yield 'plain http on loopback' => [
            'http://127.0.0.1:8080',
            ['DLOAD_TOKEN_127_0_0_1_8080' => 'local'],
            null,
            'Bearer local',
        ];
    }

    public static function provideServers(): \Generator
    {
        yield 'public' => [null, 'https://api.github.com/repos/org/repo/releases', 'github:org/repo'];
        yield 'explicit public' => ['https://github.com', 'https://api.github.com/repos/org/repo/releases', 'github:org/repo'];
        yield 'enterprise' => ['ghe.example.com', 'https://ghe.example.com/api/v3/repos/org/repo/releases', 'github@ghe.example.com:org/repo'];
        yield 'local fake' => ['http://127.0.0.1:8080', 'http://127.0.0.1:8080/api/v3/repos/org/repo/releases', 'github@127.0.0.1:8080:org/repo'];
    }

    /**
     * @param array<string, string> $environment
     */
    #[DataProvider('provideTokenSources')]
    #[Test]
    public function sendsTheTokenDeclaredForTheServer(
        ?string $server,
        array $environment,
        ?string $gitHubToken,
        string $authorization,
    ): void {
        $factory = $this->factory($environment, $gitHubToken);

        $this->loadReleases($factory, $server);

        Assert::same($this->lastRequest()->getHeaderLine('authorization'), $authorization);
    }

    #[DataProvider('provideServers')]
    #[Test]
    public function talksToTheApiOfTheServer(?string $server, string $releasesUrl, string $id): void
    {
        $factory = $this->factory();

        $repository = $this->loadReleases($factory, $server);

        $uri = $this->lastRequest()->getUri();
        Assert::same($uri->getScheme() . '://' . $uri->getAuthority() . $uri->getPath(), $releasesUrl);
        Assert::same((string) $repository->getId(), $id);
        Assert::same($repository->getName(), 'org/repo');
    }

    #[Test]
    public function rejectsAMalformedServer(): never
    {
        $config = self::config('https://ghe.example.com/api/v3');

        Expect::exception(\InvalidArgumentException::class)->withMessageContaining('Repository `org/repo`');

        $this->factory()->create($config);
    }

    private static function config(?string $server): RepositoryConfig
    {
        $config = new RepositoryConfig();
        $config->uri = 'org/repo';
        $config->server = $server;

        return $config;
    }

    /**
     * @param array<string, string> $environment
     */
    private function factory(array $environment = [], ?string $gitHubToken = null): Factory
    {
        $this->http = new RecordingHttpFactory();
        $gitHubConfig = new GitHubConfig();
        $gitHubConfig->token = $gitHubToken;

        return new Factory($this->http, $gitHubConfig, new Logger(), new PassThroughRegistry(), new ServerTokens($environment));
    }

    private function loadReleases(Factory $factory, ?string $server): Repository
    {
        $repository = $factory->create(self::config($server));
        \iterator_to_array($repository->getReleases());

        return $repository;
    }

    private function lastRequest(): RequestInterface
    {
        Assert::notBlank($this->http->sent);

        return $this->http->sent[\array_key_last($this->http->sent)];
    }
}
