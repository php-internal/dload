<?php

declare(strict_types=1);

namespace Internal\DLoad\Tests\Unit\Module\Software;

use Internal\DLoad\Module\Config\Schema\CustomSoftwareRegistry;
use Internal\DLoad\Module\Config\Schema\Embed\Software;
use Internal\DLoad\Module\Software\OriginKind;
use Internal\DLoad\Module\Software\SoftwareCollector;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Test;

#[Covers(SoftwareCollector::class)]
final class SoftwareCollectorTest
{
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

    private static function registry(bool $overwrite, Software ...$software): CustomSoftwareRegistry
    {
        $registry = new CustomSoftwareRegistry();
        $registry->overwrite = $overwrite;
        $registry->software = $software;

        return $registry;
    }
}
