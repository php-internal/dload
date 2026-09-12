<?php

declare(strict_types=1);

namespace Internal\DLoad\Tests\Integration\Module\Registry;

use Internal\DLoad\Bootstrap;
use Internal\DLoad\Module\Registry\Internal\PassThroughRegistry;
use Internal\DLoad\Module\Registry\Internal\StoredVersionRegistry;
use Internal\DLoad\Module\Registry\Record\RepositoryRecord;
use Internal\DLoad\Module\Registry\RegistryStorage;
use Internal\DLoad\Module\Registry\RepositoryId;
use Internal\DLoad\Module\Registry\VersionRegistry;
use Internal\DLoad\Module\Common\FileSystem\FS;
use Internal\Container\Container;
use Internal\Path;
use Testo\Assert;
use Testo\Codecov\Covers;
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
        Assert::true(\is_file($this->directory . '/dload/repositories/github/a/b.json'));
    }

    #[Test]
    public function environmentVariableSetsTheDirectory(): void
    {
        $container = self::bootstrap(environment: ['DLOAD_CACHE_DIR' => $this->directory]);

        $container->get(RegistryStorage::class)->save(RepositoryRecord::empty(new RepositoryId('github', 'a/b')));
        Assert::true(\is_file($this->directory . '/repositories/github/a/b.json'));
    }

    #[Test]
    public function xmlAttributeSetsTheDirectory(): void
    {
        $container = self::bootstrap(xml: \sprintf('<?xml version="1.0"?><dload cache-dir="%s"/>', $this->directory));

        $container->get(RegistryStorage::class)->save(RepositoryRecord::empty(new RepositoryId('github', 'a/b')));
        Assert::true(\is_file($this->directory . '/repositories/github/a/b.json'));
    }

    #[Test]
    public function zeroTtlDisablesTheRegistry(): void
    {
        $container = self::bootstrap(environment: ['DLOAD_CACHE_DIR' => $this->directory, 'DLOAD_CACHE_TTL' => '0']);

        Assert::instanceOf($container->get(VersionRegistry::class), PassThroughRegistry::class);
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
