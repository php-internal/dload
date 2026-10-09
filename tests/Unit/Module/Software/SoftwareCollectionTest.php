<?php

declare(strict_types=1);

namespace Internal\DLoad\Tests\Unit\Module\Software;

use Internal\DLoad\Module\Config\Schema\Embed\Software;
use Internal\DLoad\Module\Software\Origin;
use Internal\DLoad\Module\Software\SoftwareCollection;
use Testo\Assert;
use Testo\Codecov\Covers;
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

    /**
     * @param non-empty-string $id
     */
    private static function software(string $id, string $description): Software
    {
        return Software::fromArray(['name' => $id, 'description' => $description]);
    }
}
