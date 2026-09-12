<?php

declare(strict_types=1);

namespace Internal\DLoad\Tests\Unit\Module\Repository\Internal\GitHub;

use Internal\DLoad\Module\Cache\Internal\NullResponseCache;
use Internal\DLoad\Module\Config\Schema\Embed\Repository as RepositoryConfig;
use Internal\DLoad\Module\Config\Schema\GitHub as GitHubConfig;
use Internal\DLoad\Module\Repository\Internal\GitHub\Factory;
use Internal\DLoad\Service\Logger;
use Internal\DLoad\Tests\Unit\Module\Repository\Internal\GitHub\Stub\HttpFactoryStub;
use Mockery\MockInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\UriInterface;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Data\DataProvider;
use Testo\Lifecycle\BeforeTest;
use Testo\Test;

#[Covers(Factory::class)]
final class FactoryTest
{
    private Factory $factory;

    public static function provideSupportedTypes(): \Generator
    {
        yield 'lowercase' => ['github', true];
        yield 'mixed case' => ['GitHub', true];
        yield 'uppercase' => ['GITHUB', true];
        yield 'gitlab' => ['gitlab', false];
        yield 'unknown' => ['custom', false];
    }

    public static function provideRepositoryUris(): \Generator
    {
        yield 'bare repository path' => ['owner/repo', 'owner/repo'];
        yield 'full url' => ['https://github.com/owner/repo', 'owner/repo'];
    }

    #[DataProvider('provideSupportedTypes')]
    #[Test]
    public function supportsOnlyGithubRegardlessOfCase(string $type, bool $expected): void
    {
        $config = new RepositoryConfig();
        $config->type = $type;
        $config->uri = 'owner/repo';

        Assert::same($this->factory->supports($config), $expected);
    }

    #[DataProvider('provideRepositoryUris')]
    #[Test]
    public function createDerivesTheRepositoryNameFromTheUri(string $uri, string $expectedName): void
    {
        $config = new RepositoryConfig();
        $config->type = 'github';
        $config->uri = $uri;

        Assert::same($this->factory->create($config)->getName(), $expectedName);
    }

    #[BeforeTest]
    protected function prepare(): void
    {
        $this->factory = new Factory(
            new HttpFactoryStub(
                static fn(): UriInterface&MockInterface => \Mockery::mock(UriInterface::class)->shouldIgnoreMissing(),
                static fn(): RequestInterface&MockInterface => \Mockery::mock(RequestInterface::class)->shouldIgnoreMissing(),
                static fn(): ClientInterface&MockInterface => \Mockery::mock(ClientInterface::class)->shouldIgnoreMissing(),
            ),
            new GitHubConfig(),
            new Logger(),
            new NullResponseCache(),
        );
    }
}
