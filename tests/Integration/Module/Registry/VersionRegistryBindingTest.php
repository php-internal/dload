<?php

declare(strict_types=1);

namespace Internal\DLoad\Tests\Integration\Module\Registry;

use Internal\DLoad\Bootstrap;
use Internal\DLoad\Module\Config\Schema\Embed\Repository as RepositoryConfig;
use Internal\DLoad\Module\Registry\Internal\PassThroughRegistry;
use Internal\DLoad\Module\Registry\Internal\StoredVersionRegistry;
use Internal\DLoad\Module\Registry\Record\RepositoryRecord;
use Internal\DLoad\Module\Registry\RegistryStorage;
use Internal\DLoad\Module\Registry\RepositoryId;
use Internal\DLoad\Module\Registry\VersionRegistry;
use Internal\DLoad\Module\Repository\Internal\GitHub\GitHubRepository;
use Internal\DLoad\Module\Repository\Internal\GitLab\GitLabRepository;
use Internal\DLoad\Module\Repository\RepositoryProvider;
use Internal\DLoad\Module\Common\FileSystem\FS;
use Internal\Container\Container;
use Internal\Path;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Expect;
use Testo\Filter\Group;
use Testo\Lifecycle\AfterTest;
use Testo\Lifecycle\BeforeTest;
use Testo\Test;

/**
 * The registry is only useful when the container actually hands out the configured implementation,
 * so the binding is verified through a real bootstrap rather than by constructing it by hand.
 */
#[Group('integration')]
#[Covers(Bootstrap::class)]
final class VersionRegistryBindingTest
{
    private string $directory;

    #[Test]
    public function registryIsEnabledByDefaultInTheUserCacheDirectory(): void
    {
        $container = self::bootstrap(environment: ['XDG_CACHE_HOME' => $this->directory]);

        Assert::instanceOf($container->get(VersionRegistry::class), StoredVersionRegistry::class);

        $container->get(RegistryStorage::class)->save(RepositoryRecord::empty(new RepositoryId('github', 'a/b')));
        Assert::true(\is_file($this->directory . '/dload/repositories/github/a/b/index.json'));
    }

    #[Test]
    public function environmentVariableSetsTheDirectory(): void
    {
        $container = self::bootstrap(environment: ['DLOAD_CACHE_DIR' => $this->directory]);

        $container->get(RegistryStorage::class)->save(RepositoryRecord::empty(new RepositoryId('github', 'a/b')));
        Assert::true(\is_file($this->directory . '/repositories/github/a/b/index.json'));
    }

    #[Test]
    public function xmlAttributeSetsTheDirectory(): void
    {
        $container = self::bootstrap(xml: \sprintf('<?xml version="1.0"?><dload cache-dir="%s"/>', $this->directory));

        $container->get(RegistryStorage::class)->save(RepositoryRecord::empty(new RepositoryId('github', 'a/b')));
        Assert::true(\is_file($this->directory . '/repositories/github/a/b/index.json'));
    }

    #[Test]
    public function environmentOverridesTheXmlAttribute(): void
    {
        $container = self::bootstrap(
            xml: '<?xml version="1.0"?><dload cache-dir="/nowhere" cache-ttl="900"/>',
            environment: ['DLOAD_CACHE_DIR' => $this->directory, 'DLOAD_CACHE_TTL' => '0'],
        );

        Assert::instanceOf($container->get(VersionRegistry::class), PassThroughRegistry::class);

        $container->get(RegistryStorage::class)->save(RepositoryRecord::empty(new RepositoryId('github', 'a/b')));
        Assert::true(\is_file($this->directory . '/repositories/github/a/b/index.json'));
    }

    #[Test]
    public function zeroTtlDisablesTheRegistry(): void
    {
        $container = self::bootstrap(environment: ['DLOAD_CACHE_DIR' => $this->directory, 'DLOAD_CACHE_TTL' => '0']);

        Assert::instanceOf($container->get(VersionRegistry::class), PassThroughRegistry::class);
    }

    #[Test]
    public function repositoryProviderIsBoundWithGithubAndGitLabFactories(): void
    {
        $provider = self::bootstrap()->get(RepositoryProvider::class);

        Assert::instanceOf($provider, RepositoryProvider::class);
        Assert::instanceOf(
            $provider->getByConfig(RepositoryConfig::fromArray(['type' => 'github', 'uri' => 'a/b'])),
            GitHubRepository::class,
        );
        Assert::instanceOf(
            $provider->getByConfig(RepositoryConfig::fromArray(['type' => 'gitlab', 'uri' => 'a/b'])),
            GitLabRepository::class,
        );
    }

    #[Test]
    public function xmlConfigIsReadFromAFilePath(): void
    {
        $configFile = \sys_get_temp_dir() . '/dload-config-' . \bin2hex(\random_bytes(6)) . '.xml';
        \file_put_contents($configFile, \sprintf('<?xml version="1.0"?><dload cache-dir="%s"/>', $this->directory));

        try {
            $container = self::bootstrap(xml: $configFile);

            $container->get(RegistryStorage::class)->save(RepositoryRecord::empty(new RepositoryId('github', 'a/b')));
            Assert::true(\is_file($this->directory . '/repositories/github/a/b/index.json'));
        } finally {
            \unlink($configFile);
        }
    }

    #[Test]
    public function missingConfigFilePathThrows(): void
    {
        Expect::exception(\InvalidArgumentException::class)->withMessage('Config file not found.');

        self::bootstrap(xml: \sys_get_temp_dir() . '/dload-missing-' . \bin2hex(\random_bytes(6)) . '.xml');
    }

    #[BeforeTest]
    protected function prepare(): void
    {
        $this->directory = \sys_get_temp_dir() . '/dload-registry-binding-' . \bin2hex(\random_bytes(6));
    }

    #[AfterTest]
    protected function cleanup(): void
    {
        \is_dir($this->directory) and FS::removeDir(Path::create($this->directory));
    }

    /**
     * @param array<string, string> $environment
     */
    private static function bootstrap(?string $xml = null, array $environment = []): Container
    {
        return Bootstrap::init()
            ->withConfig(xml: $xml, environment: $environment)
            ->finish();
    }
}
