<?php

declare(strict_types=1);

namespace Internal\DLoad\Tests\Unit\Module\Repository\Internal\GitLab;

use Internal\DLoad\Module\Config\Schema\Embed\Repository as RepositoryConfig;
use Internal\DLoad\Module\Config\Schema\GitLab as GitLabConfig;
use Internal\DLoad\Module\Registry\Internal\PassThroughRegistry;
use Internal\DLoad\Module\Repository\Internal\GitLab\Factory;
use Internal\DLoad\Module\Repository\Internal\ServerTokens;
use Internal\DLoad\Module\Repository\Repository;
use Internal\DLoad\Service\Logger;
use Internal\DLoad\Tests\Unit\Module\Repository\Internal\GitLab\Stub\HttpFactoryStub;
use Internal\DLoad\Tests\Unit\Module\Repository\Stub\RecordingHttpFactory;
use Psr\Http\Message\RequestInterface;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Data\DataProvider;
use Testo\Expect;
use Testo\Lifecycle\BeforeTest;
use Testo\Test;

#[Covers(Factory::class)]
final class FactoryTest
{
    private Factory $factory;
    private RecordingHttpFactory $http;

    public static function provideSupportedTypes(): \Generator
    {
        yield 'lowercase' => ['gitlab', true];
        yield 'mixed case' => ['GitLab', true];
        yield 'uppercase' => ['GITLAB', true];
        yield 'github' => ['github', false];
        yield 'unknown' => ['custom', false];
    }

    public static function provideRepositoryUris(): \Generator
    {
        yield 'bare project path' => ['group/project', 'group/project'];
        yield 'full url' => ['https://gitlab.com/group/project', '/group/project'];
        yield 'nested group' => ['https://gitlab.com/group/sub/project', '/group/sub/project'];

        # `parse_url()` returns null for a URL without a path and false for one it cannot parse at
        # all; both have to fall back to the configured value rather than be passed on.
        yield 'url without a path' => ['https://gitlab.com', 'https://gitlab.com'];
        yield 'unparsable url' => ['http://:80', 'http://:80'];
        yield 'scheme only' => ['https://', 'https://'];
    }

    public static function provideTokenSources(): \Generator
    {
        yield 'no token' => [null, [], null, ''];
        yield 'GITLAB_TOKEN on the public host' => [null, [], 'public', 'Bearer public'];
        yield 'server variable wins over GITLAB_TOKEN' => [
            null,
            ['DLOAD_TOKEN_GITLAB_COM' => 'own'],
            'public',
            'Bearer own',
        ];
        yield 'GITLAB_TOKEN never leaves the public host' => ['gitlab.example.com', [], 'public', ''];
        yield 'custom server variable' => [
            'gitlab.example.com:8443',
            ['DLOAD_TOKEN_GITLAB_EXAMPLE_COM_8443' => 'own'],
            'public',
            'Bearer own',
        ];
        yield 'no token over plain http' => [
            'http://gitlab.example.com',
            ['DLOAD_TOKEN_GITLAB_EXAMPLE_COM' => 'own'],
            null,
            '',
        ];
        yield 'plain http on loopback' => [
            'http://localhost:8080',
            ['DLOAD_TOKEN_LOCALHOST_8080' => 'local'],
            null,
            'Bearer local',
        ];
    }

    public static function provideServers(): \Generator
    {
        yield 'public' => [null, 'https://gitlab.com/api/v4/projects/group%2Fproject/releases', 'gitlab:group/project'];
        yield 'self-hosted' => [
            'gitlab.example.com:8443',
            'https://gitlab.example.com:8443/api/v4/projects/group%2Fproject/releases',
            'gitlab@gitlab.example.com:8443:group/project',
        ];
    }

    #[DataProvider('provideSupportedTypes')]
    #[Test]
    public function supportsOnlyGitlabRegardlessOfCase(string $type, bool $expected): void
    {
        $config = new RepositoryConfig();
        $config->type = $type;
        $config->uri = 'group/project';

        Assert::same($this->factory->supports($config), $expected);
    }

    #[DataProvider('provideRepositoryUris')]
    #[Test]
    public function createDerivesTheProjectPathFromTheUri(string $uri, string $expectedPath): void
    {
        $config = new RepositoryConfig();
        $config->type = 'gitlab';
        $config->uri = $uri;

        $repository = $this->factory->create($config);

        Assert::same($repository->getName(), $expectedPath);
    }

    /**
     * @param array<string, string> $environment
     */
    #[DataProvider('provideTokenSources')]
    #[Test]
    public function sendsTheTokenDeclaredForTheServer(
        ?string $server,
        array $environment,
        ?string $gitLabToken,
        string $authorization,
    ): void {
        $factory = $this->recordingFactory($environment, $gitLabToken);

        $this->loadReleases($factory, $server);

        Assert::same($this->lastRequest()->getHeaderLine('authorization'), $authorization);
    }

    #[DataProvider('provideServers')]
    #[Test]
    public function talksToTheApiOfTheServer(?string $server, string $releasesUrl, string $id): void
    {
        $factory = $this->recordingFactory();

        $repository = $this->loadReleases($factory, $server);

        $uri = $this->lastRequest()->getUri();
        Assert::same($uri->getScheme() . '://' . $uri->getAuthority() . $uri->getPath(), $releasesUrl);
        Assert::same((string) $repository->getId(), $id);
    }

    #[Test]
    public function rejectsAMalformedServer(): never
    {
        $config = self::config('ftp://gitlab.example.com');

        Expect::exception(\InvalidArgumentException::class)->withMessageContaining('Repository `group/project`');

        $this->factory->create($config);
    }

    #[BeforeTest]
    protected function prepare(): void
    {
        $this->factory = new Factory(
            new HttpFactoryStub(),
            new GitLabConfig(),
            new Logger(),
            new PassThroughRegistry(),
            new ServerTokens([]),
        );
    }

    private static function config(?string $server): RepositoryConfig
    {
        $config = new RepositoryConfig();
        $config->type = 'gitlab';
        $config->uri = 'group/project';
        $config->server = $server;

        return $config;
    }

    /**
     * @param array<string, string> $environment
     */
    private function recordingFactory(array $environment = [], ?string $gitLabToken = null): Factory
    {
        $this->http = new RecordingHttpFactory();
        $gitLabConfig = new GitLabConfig();
        $gitLabConfig->token = $gitLabToken;

        return new Factory($this->http, $gitLabConfig, new Logger(), new PassThroughRegistry(), new ServerTokens($environment));
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
