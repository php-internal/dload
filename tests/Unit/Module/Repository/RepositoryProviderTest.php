<?php

declare(strict_types=1);

namespace Internal\DLoad\Tests\Unit\Module\Repository;

use Internal\DLoad\Module\Config\Schema\Embed\Repository as RepositoryConfig;
use Internal\DLoad\Module\Repository\Repository;
use Internal\DLoad\Module\Repository\RepositoryFactory;
use Internal\DLoad\Module\Repository\RepositoryProvider;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Data\DataProvider;
use Testo\Expect;
use Testo\Lifecycle\BeforeTest;
use Testo\Test;

#[Covers(RepositoryProvider::class)]
final class RepositoryProviderTest
{
    private RepositoryProvider $repositoryProvider;

    public static function provideRepositoryConfigs(): \Generator
    {
        $githubConfig = new RepositoryConfig();
        $githubConfig->type = 'github';
        $githubConfig->uri = 'vendor/package';

        $gitlabConfig = new RepositoryConfig();
        $gitlabConfig->type = 'gitlab';
        $gitlabConfig->uri = 'group/project';
        $gitlabConfig->assetPattern = '/^release-.*$/';

        $customConfig = new RepositoryConfig();
        $customConfig->type = 'custom';
        $customConfig->uri = 'https://example.com/repo';

        yield 'github config with support' => [$githubConfig, true];
        yield 'gitlab config with support' => [$gitlabConfig, true];
        yield 'custom config without support' => [$customConfig, false];
    }

    #[Test]
    public function addRepositoryFactoryReturnsSelf(): void
    {
        $factory = \Mockery::mock(RepositoryFactory::class);

        $result = $this->repositoryProvider->addRepositoryFactory($factory);

        Assert::same($result, $this->repositoryProvider);
    }

    #[Test]
    public function getByConfigReturnsRepositoryFromSupportingFactory(): void
    {
        $config = new RepositoryConfig();
        $config->type = 'github';
        $config->uri = 'vendor/package';

        $repository = \Mockery::mock(Repository::class);

        $unsupportedFactory = \Mockery::mock(RepositoryFactory::class);
        $unsupportedFactory->allows('supports')->with($config)->andReturn(false);
        $unsupportedFactory->shouldNotReceive('create');

        $supportedFactory = \Mockery::mock(RepositoryFactory::class);
        $supportedFactory->allows('supports')->with($config)->andReturn(true);
        $supportedFactory->allows('create')->with($config)->andReturn($repository);

        // Add factories to provider (order matters - first unsupported, then supported)
        $this->repositoryProvider->addRepositoryFactory($unsupportedFactory);
        $this->repositoryProvider->addRepositoryFactory($supportedFactory);

        $result = $this->repositoryProvider->getByConfig($config);

        Assert::same($result, $repository);
    }

    #[Test]
    public function getByConfigUsesFirstSupportingFactory(): void
    {
        $config = new RepositoryConfig();
        $config->type = 'github';
        $config->uri = 'vendor/package';

        $repository1 = \Mockery::mock(Repository::class);
        $repository2 = \Mockery::mock(Repository::class);

        $firstFactory = \Mockery::mock(RepositoryFactory::class);
        $firstFactory->allows('supports')->with($config)->andReturn(true);
        $firstFactory->allows('create')->with($config)->andReturn($repository1);

        $secondFactory = \Mockery::mock(RepositoryFactory::class);
        $secondFactory->allows('supports')->with($config)->andReturn(true);
        $secondFactory->shouldNotReceive('create');

        // Add both factories (both support the config, but first one should be used)
        $this->repositoryProvider->addRepositoryFactory($firstFactory);
        $this->repositoryProvider->addRepositoryFactory($secondFactory);

        $result = $this->repositoryProvider->getByConfig($config);

        Assert::same($result, $repository1);
    }

    #[Test]
    public function getByConfigThrowsExceptionWhenNoFactorySupportsConfig(): void
    {
        $config = new RepositoryConfig();
        $config->type = 'unsupported';
        $config->uri = 'vendor/package';

        $factory = \Mockery::mock(RepositoryFactory::class);
        $factory->allows('supports')->with($config)->andReturn(false);
        $this->repositoryProvider->addRepositoryFactory($factory);

        Expect::exception(\RuntimeException::class)->withMessage("No factory found for repository type `unsupported`.");

        $this->repositoryProvider->getByConfig($config);
    }

    #[DataProvider('provideRepositoryConfigs')]
    #[Test]
    public function getByConfigWithVariousConfigs(RepositoryConfig $config, bool $factorySupports): void
    {
        $repository = \Mockery::mock(Repository::class);

        $factory = \Mockery::mock(RepositoryFactory::class);
        $factory->allows('supports')->with($config)->andReturn($factorySupports);

        if ($factorySupports) {
            $factory->allows('create')->with($config)->andReturn($repository);
        }

        $this->repositoryProvider->addRepositoryFactory($factory);

        // Assert expectation for exception if no factory supports
        if (!$factorySupports) {
            Expect::exception(\RuntimeException::class)->withMessage("No factory found for repository type `{$config->type}`.");
        }

        $result = $this->repositoryProvider->getByConfig($config);

        // Assert result if factory supports
        if ($factorySupports) {
            Assert::same($result, $repository);
        }
    }

    #[BeforeTest]
    protected function prepare(): void
    {
        $this->repositoryProvider = new RepositoryProvider();
    }
}
