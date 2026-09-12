<?php

declare(strict_types=1);

namespace Internal\DLoad\Tests\Unit\Module\Repository\Internal\GitLab;

use Internal\DLoad\Module\Cache\Internal\NullResponseCache;
use Internal\DLoad\Module\Config\Schema\Embed\Repository as RepositoryConfig;
use Internal\DLoad\Module\Config\Schema\GitLab as GitLabConfig;
use Internal\DLoad\Module\Repository\Internal\GitLab\Factory;
use Internal\DLoad\Service\Logger;
use Internal\DLoad\Tests\Unit\Module\Repository\Internal\GitLab\Stub\HttpFactoryStub;
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

    #[BeforeTest]
    protected function prepare(): void
    {
        $this->factory = new Factory(
            new HttpFactoryStub(),
            new GitLabConfig(),
            new Logger(),
            new NullResponseCache(),
        );
    }
}
