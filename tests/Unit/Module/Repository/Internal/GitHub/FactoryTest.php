<?php

declare(strict_types=1);

namespace Internal\DLoad\Tests\Unit\Module\Repository\Internal\GitHub;

use Internal\DLoad\Module\Config\Schema\Embed\Repository as RepositoryConfig;
use Internal\DLoad\Module\Config\Schema\GitHub as GitHubConfig;
use Internal\DLoad\Module\Registry\Internal\PassThroughRegistry;
use Internal\DLoad\Module\Repository\Exception\InvalidRepositoryConfigException;
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
        yield 'no token' => ['org/repo', [], null, ''];
        yield 'GITHUB_TOKEN on the public host' => ['org/repo', [], 'public', 'Bearer public'];
        yield 'server variable wins over GITHUB_TOKEN' => [
            'org/repo',
            ['DLOAD_TOKEN_GITHUB_COM' => 'own'],
            'public',
            'Bearer own',
        ];
        yield 'empty server variable is ignored' => ['org/repo', ['DLOAD_TOKEN_GITHUB_COM' => ''], 'public', 'Bearer public'];
        yield 'GITHUB_TOKEN never leaves the public host' => ['https://ghe.example.com/org/repo', [], 'public', ''];
        yield 'custom server variable' => [
            'https://ghe.example.com/org/repo',
            ['DLOAD_TOKEN_GHE_EXAMPLE_COM' => 'ghe'],
            'public',
            'Bearer ghe',
        ];
        yield 'variable of another server' => [
            'https://ghe.example.com/org/repo',
            ['DLOAD_TOKEN_GITHUB_COM' => 'own', 'DLOAD_TOKEN_OTHER_EXAMPLE_COM' => 'other'],
            null,
            '',
        ];
        yield 'no token over plain http' => [
            'http://ghe.example.com/org/repo',
            ['DLOAD_TOKEN_GHE_EXAMPLE_COM' => 'ghe'],
            null,
            '',
        ];
        yield 'plain http on loopback' => [
            'http://127.0.0.1:8080/org/repo',
            ['DLOAD_TOKEN_127_0_0_1___8080' => 'local'],
            null,
            'Bearer local',
        ];
    }

    public static function provideServers(): \Generator
    {
        $public = 'https://api.github.com/repos/org/repo/releases';
        yield 'path' => ['org/repo', $public, 'github:org/repo'];
        yield 'public url' => ['https://github.com/org/repo', $public, 'github:org/repo'];
        yield 'public host without a scheme' => ['github.com/org/repo', $public, 'github:org/repo'];
        yield 'public clone url over http' => ['http://GitHub.com/org/repo.git/', $public, 'github:org/repo'];
        yield 'enterprise' => [
            'https://ghe.example.com/org/repo',
            'https://ghe.example.com/api/v3/repos/org/repo/releases',
            'github@ghe.example.com:org/repo',
        ];
        yield 'enterprise clone url with a port' => [
            'https://ghe.example.com:8443/org/repo.git',
            'https://ghe.example.com:8443/api/v3/repos/org/repo/releases',
            'github@ghe.example.com:8443:org/repo',
        ];
        yield 'local fake' => [
            'http://127.0.0.1:8080/org/repo',
            'http://127.0.0.1:8080/api/v3/repos/org/repo/releases',
            'github@127.0.0.1:8080:org/repo',
        ];
    }

    public static function provideInvalidUris(): \Generator
    {
        yield 'single segment' => ['repo'];
        yield 'page of a repository' => ['https://github.com/org/repo/releases'];
        yield 'host without a scheme' => ['ghe.example.com/org/repo'];
        yield 'server without a path' => ['https://ghe.example.com'];
        yield 'unsupported scheme' => ['ftp://ghe.example.com/org/repo'];
        yield 'credentials' => ['https://user:pass@ghe.example.com/org/repo'];
        yield 'malformed host' => ['https://ghe_example.com/org/repo'];
        yield 'query' => ['https://ghe.example.com/org/repo?tab=readme'];
        yield 'empty segment' => ['org//repo'];
        yield 'dot segment' => ['https://ghe.example.com/org/..'];
    }

    /**
     * @param array<string, string> $environment
     */
    #[DataProvider('provideTokenSources')]
    #[Test]
    public function sendsTheTokenDeclaredForTheServer(
        string $uri,
        array $environment,
        ?string $gitHubToken,
        string $authorization,
    ): void {
        $factory = $this->factory($environment, $gitHubToken);

        $this->loadReleases($factory, $uri);

        Assert::same($this->lastRequest()->getHeaderLine('authorization'), $authorization);
    }

    #[DataProvider('provideServers')]
    #[Test]
    public function talksToTheApiOfTheServer(string $uri, string $releasesUrl, string $id): void
    {
        $factory = $this->factory();

        $repository = $this->loadReleases($factory, $uri);

        $uri = $this->lastRequest()->getUri();
        Assert::same($uri->getScheme() . '://' . $uri->getAuthority() . $uri->getPath(), $releasesUrl);
        Assert::same((string) $repository->getId(), $id);
        Assert::same($repository->getName(), 'org/repo');
    }

    #[DataProvider('provideInvalidUris')]
    #[Test]
    public function rejectsAnUriThatIsNotARepository(string $uri): never
    {
        Expect::exception(InvalidRepositoryConfigException::class)->withMessageContaining("Repository `{$uri}`:");

        $this->factory()->create(self::config($uri));
    }

    private static function config(string $uri): RepositoryConfig
    {
        $config = new RepositoryConfig();
        $config->uri = $uri;

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

    private function loadReleases(Factory $factory, string $uri): Repository
    {
        $repository = $factory->create(self::config($uri));
        \iterator_to_array($repository->getReleases());

        return $repository;
    }

    private function lastRequest(): RequestInterface
    {
        Assert::notBlank($this->http->sent);

        return $this->http->sent[\array_key_last($this->http->sent)];
    }
}
