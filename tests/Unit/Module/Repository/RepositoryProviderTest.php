<?php

declare(strict_types=1);

namespace Internal\DLoad\Tests\Unit\Module\Repository;

use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Data\DataProvider;
use Testo\Expect;
use Testo\Lifecycle\BeforeTest;
use Testo\Test;
use Internal\DLoad\Module\Config\Schema\Embed\Repository as RepositoryConfig;
use Internal\DLoad\Module\Repository\Repository;
use Internal\DLoad\Module\Repository\RepositoryFactory;
use Internal\DLoad\Module\Repository\RepositoryProvider;

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
        $factory = $this->createMock(RepositoryFactory::class);

        $result = $this->repositoryProvider->addRepositoryFactory($factory);

        Assert::same($result, $this->repositoryProvider);
    }

    #[Test]
    public function getByConfigReturnsRepositoryFromSupportingFactory(): void
    {
        $config = new RepositoryConfig();
        $config->type = 'github';
        $config->uri = 'vendor/package';

        $repository = $this->createMock(Repository::class);

        $unsupportedFactory = $this->createMock(RepositoryFactory::class);
        $unsupportedFactory->method('supports')->with($config)->willReturn(false);
        $unsupportedFactory->expects(self::never())->method('create');

        $supportedFactory = $this->createMock(RepositoryFactory::class);
        $supportedFactory->method('supports')->with($config)->willReturn(true);
        $supportedFactory->method('create')->with($config)->willReturn($repository);

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

        $repository1 = $this->createMock(Repository::class);
        $repository2 = $this->createMock(Repository::class);

        $firstFactory = $this->createMock(RepositoryFactory::class);
        $firstFactory->method('supports')->with($config)->willReturn(true);
        $firstFactory->method('create')->with($config)->willReturn($repository1);

        $secondFactory = $this->createMock(RepositoryFactory::class);
        $secondFactory->method('supports')->with($config)->willReturn(true);
        $secondFactory->expects(self::never())->method('create');

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

        $factory = $this->createMock(RepositoryFactory::class);
        $factory->method('supports')->with($config)->willReturn(false);
        $this->repositoryProvider->addRepositoryFactory($factory);

        Expect::exception(\RuntimeException::class)->withMessage("No factory found for repository type `unsupported`.");

        $this->repositoryProvider->getByConfig($config);
    }

    #[DataProvider('provideRepositoryConfigs')]
    #[Test]
    public function getByConfigWithVariousConfigs(RepositoryConfig $config, bool $factorySupports): void
    {
        $repository = $this->createMock(Repository::class);

        $factory = $this->createMock(RepositoryFactory::class);
        $factory->method('supports')->with($config)->willReturn($factorySupports);

        if ($factorySupports) {
            $factory->method('create')->with($config)->willReturn($repository);
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
