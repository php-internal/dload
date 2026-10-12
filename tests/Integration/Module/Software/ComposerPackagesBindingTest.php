<?php

declare(strict_types=1);

namespace Internal\DLoad\Tests\Integration\Module\Software;

use Internal\DLoad\Bootstrap;
use Internal\DLoad\Module\Common\FileSystem\FS;
use Internal\DLoad\Module\Software\Entry;
use Internal\DLoad\Module\Software\SoftwareCollection;
use Internal\Path;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Filter\Group;
use Testo\Lifecycle\AfterTest;
use Testo\Lifecycle\BeforeTest;
use Testo\Test;

/**
 * Where the bootstrapped collection looks for the project's Composer packages.
 */
#[Group('integration')]
#[Covers(Bootstrap::class)]
final class ComposerPackagesBindingTest
{
    private string $directory;
    private string $cwd;

    #[Test]
    public function theProjectIsTheDirectoryOfTheConfigFile(): void
    {
        $this->project('elsewhere', 'acme/cwd');
        $this->project('project/sub', 'acme/app');
        \file_put_contents($this->directory . '/project/sub/dload.xml', '<?xml version="1.0"?><dload/>');
        \chdir($this->directory . '/elsewhere');

        $collection = self::collection($this->directory . '/project/sub/dload.xml');

        Assert::same(self::origins($collection, 'tool'), ['acme/app']);
    }

    #[Test]
    public function withoutAConfigFileTheProjectIsTheWorkingDirectory(): void
    {
        $this->project('elsewhere', 'acme/cwd');
        \chdir($this->directory . '/elsewhere');

        Assert::same(self::origins(self::collection(null), 'tool'), ['acme/cwd']);
        Assert::same(self::origins(self::collection('<?xml version="1.0"?><dload/>'), 'tool'), ['acme/cwd']);
    }

    #[Test]
    public function theRootRegistryThatIsTheConfigFileIsAddedOnce(): void
    {
        \mkdir($this->directory . '/project');
        \file_put_contents($this->directory . '/project/composer.json', (string) \json_encode([
            'name' => 'acme/app',
            'extra' => ['dload' => ['registry' => 'dload.xml']],
        ]));
        \file_put_contents(
            $this->directory . '/project/dload.xml',
            '<?xml version="1.0"?><dload><registry><software name="tool"/></registry></dload>',
        );
        \chdir($this->directory . '/project');

        $collection = self::collection('./dload.xml');

        Assert::same(self::origins($collection, 'tool'), ['dload.xml']);
    }

    #[Test]
    public function composerVendorDirOverridesTheVendorDirectory(): void
    {
        \mkdir($this->directory . '/project/libs/composer', recursive: true);
        \mkdir($this->directory . '/project/libs/acme/lib', recursive: true);
        \file_put_contents($this->directory . '/project/composer.json', (string) \json_encode(['require' => ['acme/lib' => '*']]));
        \file_put_contents($this->directory . '/project/libs/composer/installed.json', (string) \json_encode(['packages' => [[
            'name' => 'acme/lib',
            'extra' => ['dload' => ['registry' => 'dload.xml']],
            'install-path' => '../acme/lib',
        ]]]));
        \file_put_contents(
            $this->directory . '/project/libs/acme/lib/dload.xml',
            '<?xml version="1.0"?><dload><registry><software name="tool"/></registry></dload>',
        );
        \file_put_contents($this->directory . '/project/dload.xml', '<?xml version="1.0"?><dload/>');

        $collection = self::collection($this->directory . '/project/dload.xml', ['COMPOSER_VENDOR_DIR' => 'libs']);

        Assert::same(self::origins($collection, 'tool'), ['acme/lib']);
    }

    #[BeforeTest]
    protected function prepare(): void
    {
        $this->cwd = (string) \getcwd();
        $this->directory = \sys_get_temp_dir() . \DIRECTORY_SEPARATOR . 'dload-packages-binding-' . \bin2hex(\random_bytes(6));
        \mkdir($this->directory, recursive: true);
    }

    #[AfterTest]
    protected function cleanup(): void
    {
        \chdir($this->cwd);
        \is_dir($this->directory) and FS::removeDir(Path::create($this->directory));
    }

    /**
     * @param array<string, string> $environment
     */
    private static function collection(?string $xml, array $environment = []): SoftwareCollection
    {
        return Bootstrap::init()
            ->withConfig(xml: $xml, environment: $environment)
            ->finish()
            ->get(SoftwareCollection::class);
    }

    /**
     * @param non-empty-string $id
     * @return list<string> Origin of every entry of the id.
     */
    private static function origins(SoftwareCollection $collection, string $id): array
    {
        return \array_map(static fn(Entry $entry): string => (string) $entry->origin, $collection->candidates($id));
    }

    /**
     * Writes a root package that declares `tool` in its own registry file.
     */
    private function project(string $dir, string $name): void
    {
        $path = $this->directory . '/' . $dir;
        \mkdir($path, recursive: true);
        \file_put_contents($path . '/composer.json', (string) \json_encode([
            'name' => $name,
            'extra' => ['dload' => ['registry' => 'registry.xml']],
        ]));
        \file_put_contents(
            $path . '/registry.xml',
            '<?xml version="1.0"?><dload><registry><software name="tool"/></registry></dload>',
        );
    }
}
