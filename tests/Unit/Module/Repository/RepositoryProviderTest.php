<?php

declare(strict_types=1);

namespace Internal\DLoad\Tests\Unit\Module\Repository;

use Internal\DLoad\Module\Config\Schema\Embed\Repository as RepositoryConfig;
use Internal\DLoad\Module\Repository\Repository;
use Internal\DLoad\Module\Repository\RepositoryFactory;
use Internal\DLoad\Module\Repository\RepositoryProvider;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(RepositoryProvider::class)]
final class RepositoryProviderTest extends TestCase
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

    public function testAddRepositoryFactoryReturnsSelf(): void
    {
        // Arrange
        $factory = $this->createMock(RepositoryFactory::class);

        // Act
        $result = $this->repositoryProvider->addRepositoryFactory($factory);

        // Assert
        self::assertSame($this->repositoryProvider, $result);
    }

    public function testGetByConfigReturnsRepositoryFromSupportingFactory(): void
    {
        // Arrange
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

        // Act
        $result = $this->repositoryProvider->getByConfig($config);

        // Assert
        self::assertSame($repository, $result);
    }

    public function testGetByConfigUsesFirstSupportingFactory(): void
    {
        // Arrange
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

        // Act
        $result = $this->repositoryProvider->getByConfig($config);

        // Assert
        self::assertSame($repository1, $result);
    }

    public function testGetByConfigThrowsExceptionWhenNoFactorySupportsConfig(): void
    {
        // Arrange
        $config = new RepositoryConfig();
        $config->type = 'unsupported';
        $config->uri = 'vendor/package';

        $factory = $this->createMock(RepositoryFactory::class);
        $factory->method('supports')->with($config)->willReturn(false);
        $this->repositoryProvider->addRepositoryFactory($factory);

        // Assert (before Act for exceptions)
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("No factory found for repository type `unsupported`.");

        // Act
        $this->repositoryProvider->getByConfig($config);
    }

    #[DataProvider('provideRepositoryConfigs')]
    public function testGetByConfigWithVariousConfigs(RepositoryConfig $config, bool $factorySupports): void
    {
        // Arrange
        $repository = $this->createMock(Repository::class);

        $factory = $this->createMock(RepositoryFactory::class);
        $factory->method('supports')->with($config)->willReturn($factorySupports);

        if ($factorySupports) {
            $factory->method('create')->with($config)->willReturn($repository);
        }

        $this->repositoryProvider->addRepositoryFactory($factory);

        // Assert expectation for exception if no factory supports
        if (!$factorySupports) {
            $this->expectException(\RuntimeException::class);
            $this->expectExceptionMessage("No factory found for repository type `{$config->type}`.");
        }

        // Act
        $result = $this->repositoryProvider->getByConfig($config);

        // Assert result if factory supports
        if ($factorySupports) {
            self::assertSame($repository, $result);
        }
    }

    protected function setUp(): void
    {
        // Arrange (common setup)
        $this->repositoryProvider = new RepositoryProvider();
    }
}
