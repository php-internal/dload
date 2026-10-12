<?php

declare(strict_types=1);

namespace Internal\DLoad\Tests\Unit\Module\Software;

use Internal\DLoad\Module\Config\Schema\Embed\Software;
use Internal\DLoad\Module\Software\Entry;
use Internal\DLoad\Module\Software\Origin;
use Internal\DLoad\Module\Software\SoftwareCollection;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Data\DataSet;
use Testo\Test;

#[Covers(SoftwareCollection::class)]
final class SoftwareCollectionTest
{
    #[Test]
    public function theStrongerOriginWinsWhateverTheOrderOfAdding(): void
    {
        $vendor = self::software('gh', 'vendor');
        $official = self::software('gh', 'official');
        $local = self::software('gh', 'local');

        $collection = SoftwareCollection::empty()
            ->with(Origin::package('acme/app'), $vendor)
            ->with(Origin::official(), $official)
            ->with(Origin::config(), $local);

        Assert::same($collection->findSoftware('gh'), $local);
        Assert::same((string) $collection->find('gh')?->origin, 'dload.xml');
    }

    #[Test]
    public function aLaterDefinitionReplacesTheOneOfTheSameOrigin(): void
    {
        $first = self::software('gh', 'first');
        $second = self::software('gh', 'second');

        $collection = SoftwareCollection::empty()
            ->with(Origin::package('acme/app'), $first)
            ->with(Origin::package('ACME/App'), $second);

        Assert::same($collection->findSoftware('gh'), $second);
    }

    #[Test]
    public function iterationYieldsOneWinnerPerIdInTheOrderOfFirstAppearance(): void
    {
        $rr = self::software('rr', 'official rr');
        $gh = self::software('gh', 'local gh');

        $collection = SoftwareCollection::empty()
            ->with(Origin::package('acme/app'), self::software('gh', 'vendor gh'))
            ->with(Origin::official(), $rr)
            ->with(Origin::config(), $gh);

        Assert::same(\iterator_to_array($collection), ['gh' => $gh, 'rr' => $rr]);
        Assert::same(\count($collection), 2);
    }

    #[Test]
    public function addingDoesNotChangeTheOriginalCollection(): void
    {
        $empty = SoftwareCollection::empty();

        $empty->with(Origin::official(), self::software('gh', 'official'));

        Assert::same($empty->findSoftware('gh'), null);
        Assert::same(\count($empty), 0);
    }

    #[Test]
    #[DataSet(['acme/framework#gh', 'framework'], 'a shadowed package entry')]
    #[DataSet(['ACME/Framework#gh', 'framework'], 'the package name in another case')]
    #[DataSet(['vendor/cms#gh', 'cms'], 'the weakest package entry')]
    public function aPackageIdAddressesThePackageEntryWhateverShadowsIt(string $id, string $description): void
    {
        $collection = SoftwareCollection::empty()
            ->with(Origin::config(), self::software('gh', 'local'))
            ->with(Origin::official(), self::software('gh', 'official'))
            ->with(Origin::package('acme/framework'), self::software('gh', 'framework'))
            ->with(Origin::package('vendor/cms'), self::software('gh', 'cms'));

        $entry = $collection->find($id);

        Assert::same($entry?->software->description, $description);
        Assert::same($collection->findSoftware($id)?->description, $description);
    }

    #[Test]
    #[DataSet(['other/package#gh'], 'another package')]
    #[DataSet(['acme/framework#rr'], 'an id the package does not declare')]
    #[DataSet(['dload.xml#rr'], 'a config origin')]
    #[DataSet(['official#rr'], 'the official origin')]
    #[DataSet(['#gh'], 'no package name')]
    public function aPackageIdFindsNothingOutsideThatPackage(string $id): void
    {
        $collection = SoftwareCollection::empty()
            ->with(Origin::config(), self::software('rr', 'local'))
            ->with(Origin::official(), self::software('rr', 'official'))
            ->with(Origin::package('acme/framework'), self::software('gh', 'framework'));

        Assert::null($collection->find($id));
    }

    #[Test]
    public function aFirstAddedPackageWinsOverLaterPackages(): void
    {
        $collection = SoftwareCollection::empty()
            ->with(Origin::package('acme/framework'), self::software('gh', 'framework'))
            ->with(Origin::package('vendor/cms'), self::software('gh', 'cms'));

        Assert::same($collection->findSoftware('gh')?->description, 'framework');
    }

    #[Test]
    public function candidatesListEveryEntryOfTheIdInTheOrderOfAdding(): void
    {
        $collection = SoftwareCollection::empty()
            ->with(Origin::package('acme/framework'), self::software('gh', 'framework'))
            ->with(Origin::official(), self::software('gh', 'official'), self::software('rr', 'rr'))
            ->with(Origin::config(), self::software('gh', 'local'));

        $candidates = $collection->candidates('gh');

        Assert::same(
            \array_map(static fn(Entry $entry): string => (string) $entry->origin, $candidates),
            ['acme/framework', 'official', 'dload.xml'],
        );
        Assert::same($collection->candidates('unknown'), []);
    }

    #[Test]
    public function entriesYieldTheWinningEntryOfEveryId(): void
    {
        $collection = SoftwareCollection::empty()
            ->with(Origin::package('acme/framework'), self::software('gh', 'framework'), self::software('tool', 'tool'))
            ->with(Origin::official(), self::software('gh', 'official'));

        $entries = \iterator_to_array($collection->entries());

        Assert::same(
            \array_map(static fn(Entry $entry): string => $entry->origin . ' ' . $entry->software->description, $entries),
            ['gh' => 'official official', 'tool' => 'acme/framework tool'],
        );
    }

    /**
     * @param non-empty-string $id
     */
    private static function software(string $id, string $description): Software
    {
        return Software::fromArray(['name' => $id, 'description' => $description]);
    }
}
