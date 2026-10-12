<?php

declare(strict_types=1);

namespace Internal\DLoad\Tests\Unit\Module\Software;

use Internal\DLoad\Module\Config\Schema\CustomSoftwareRegistry;
use Internal\DLoad\Module\Config\Schema\Embed\Software;
use Internal\DLoad\Module\Software\ComposerPackages;
use Internal\DLoad\Module\Software\OriginKind;
use Internal\DLoad\Module\Software\SoftwareCollector;
use Internal\DLoad\Service\Logger;
use Internal\Path;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Data\DataSet;
use Testo\Lifecycle\AfterTest;
use Testo\Lifecycle\BeforeTest;
use Testo\Test;

#[Covers(SoftwareCollector::class)]
final class SoftwareCollectorTest
{
    private string $dir;

    #[BeforeTest]
    public function createProject(): void
    {
        $this->dir = \sys_get_temp_dir() . \DIRECTORY_SEPARATOR . 'dload-collector-' . \bin2hex(\random_bytes(6));
        \mkdir($this->dir . '/vendor/acme/lib', 0777, true);
        \mkdir($this->dir . '/vendor/composer', 0777, true);
        \file_put_contents($this->dir . '/composer.json', (string) \json_encode(['require' => ['acme/lib' => '*']]));
        \file_put_contents($this->dir . '/vendor/composer/installed.json', (string) \json_encode(['packages' => [[
            'name' => 'acme/lib',
            'extra' => ['dload' => ['registry' => 'dload.xml']],
            'install-path' => '../acme/lib',
        ]]]));
        \file_put_contents(
            $this->dir . '/vendor/acme/lib/dload.xml',
            '<dload><registry><software name="Acme Tool" alias="acme-tool"/><software name="RR" alias="rr"/><software name="Local"/></registry></dload>',
        );
    }

    #[AfterTest]
    public function removeProject(): void
    {
        foreach (['vendor/acme/lib/dload.xml', 'vendor/composer/installed.json', 'composer.json'] as $file) {
            @\unlink($this->dir . '/' . $file);
        }

        foreach (['vendor/acme/lib', 'vendor/acme', 'vendor/composer', 'vendor', ''] as $dir) {
            @\rmdir($this->dir . '/' . $dir);
        }
    }

    #[Test]
    public function configEntriesShadowTheOfficialOnes(): void
    {
        $local = Software::fromArray(['name' => 'RoadRunner', 'alias' => 'rr']);

        $collection = (new SoftwareCollector(self::registry(false, $local)))->collect();

        Assert::same($collection->findSoftware('rr'), $local);
        Assert::same($collection->find('rr')?->origin->kind, OriginKind::Config);
        Assert::same($collection->find('temporal')?->origin->kind, OriginKind::Official);
    }

    #[Test]
    public function overwriteDropsTheOfficialRegistry(): void
    {
        $local = Software::fromArray(['name' => 'Tool']);

        $collection = (new SoftwareCollector(self::registry(true, $local)))->collect();

        Assert::same(\iterator_to_array($collection), ['tool' => $local]);
    }

    #[Test]
    public function packageEntriesComeAfterTheConfigAndTheOfficialOnes(): void
    {
        $local = Software::fromArray(['name' => 'Local']);

        $collection = (new SoftwareCollector(self::registry(false, $local), $this->packages()))->collect();

        Assert::same((string) $collection->find('acme-tool')?->origin, 'acme/lib');
        Assert::same($collection->find('rr')?->origin->kind, OriginKind::Official);
        Assert::same($collection->find('local')?->software, $local);
        Assert::same((string) $collection->find('acme/lib#rr')?->origin, 'acme/lib');
        Assert::same((string) $collection->find('acme/lib#local')?->origin, 'acme/lib');
    }

    #[Test]
    #[DataSet([false, false], 'from-packages="false"')]
    #[DataSet([true, true], 'overwrite="true"')]
    public function theRegistryCanSwitchPackageEntriesOff(bool $overwrite, bool $fromPackages): void
    {
        $registry = self::registry($overwrite);
        $registry->fromPackages = $fromPackages;

        $collection = (new SoftwareCollector($registry, $this->packages()))->collect();

        Assert::null($collection->find('acme-tool'));
        Assert::null($collection->find('acme/lib#rr'));
    }

    #[Test]
    public function withoutComposerPackagesThereAreNoPackageEntries(): void
    {
        $collection = (new SoftwareCollector(self::registry(false)))->collect();

        Assert::null($collection->find('acme-tool'));
    }

    private static function registry(bool $overwrite, Software ...$software): CustomSoftwareRegistry
    {
        $registry = new CustomSoftwareRegistry();
        $registry->overwrite = $overwrite;
        $registry->software = $software;

        return $registry;
    }

    private function packages(): ComposerPackages
    {
        return new ComposerPackages(Path::create($this->dir), null, new Logger());
    }
}
