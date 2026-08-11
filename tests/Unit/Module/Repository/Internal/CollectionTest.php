<?php

declare(strict_types=1);

namespace Internal\DLoad\Tests\Unit\Module\Repository\Internal;

use Internal\DLoad\Module\Repository\Internal\Collection;
use Internal\DLoad\Module\Repository\Internal\Paginator;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Data\DataProvider;
use Testo\Test;

#[Covers(Collection::class)]
final class CollectionTest
{
    public static function provideLimitCases(): \Generator
    {
        yield 'empty collection' => [
            [], 5, [],
        ];

        yield 'limit less than collection size' => [
            [1, 2, 3, 4, 5], 3, [1, 2, 3],
        ];

        yield 'limit equal to collection size' => [
            [1, 2, 3], 3, [1, 2, 3],
        ];

        yield 'limit greater than collection size' => [
            [1, 2, 3], 5, [1, 2, 3],
        ];

        yield 'zero limit returns all items' => [
            [1, 2, 3, 4, 5], 0, [1, 2, 3, 4, 5],
        ];
    }

    #[Test]
    public function collectionWithIterable(): void
    {
        // Create a test collection class
        $testCollection = new class([]) extends Collection {};

        // Test with array
        $arrayCollection = $testCollection::create(['item1', 'item2', 'item3']);
        Assert::equals($arrayCollection->toArray(), ['item1', 'item2', 'item3']);

        // Test with generator
        $generator = static function () {
            yield 'gen1';
            yield 'gen2';
        };

        $generatorCollection = $testCollection::create($generator());
        Assert::equals($generatorCollection->toArray(), ['gen1', 'gen2']);
    }

    #[Test]
    public function collectionWithPaginator(): void
    {
        // Create a test collection class
        $testCollection = new class([]) extends Collection {};

        // Create a paginator
        $pageLoader = static function (): \Generator {
            yield ['page1-item1', 'page1-item2'];
            yield ['page2-item1', 'page2-item2'];
        };

        $paginator = Paginator::createFromGenerator($pageLoader(), null);

        // Create collection with paginator
        $collection = $testCollection::create($paginator);

        // Test toArray() loads all pages
        Assert::equals($collection->toArray(), ['page1-item1', 'page1-item2', 'page2-item1', 'page2-item2']);
    }

    #[Test]
    public function filterChaining(): void
    {
        // Create a test collection class
        $testCollection = new class([]) extends Collection {};

        // Create a collection with array
        $collection = $testCollection::create([1, 2, 3, 4, 5, 6, 7, 8, 9, 10]);

        // Apply multiple filters
        $filtered = $collection
            ->filter(static fn($item) => $item > 3)
            ->filter(static fn($item) => $item < 8)
            ->filter(static fn($item) => $item % 2 === 0);

        // Check result
        Assert::equals($filtered->toArray(), [4, 6]);
    }

    #[Test]
    public function filterWithPaginator(): void
    {
        // Create a test collection class
        $testCollection = new class([]) extends Collection {};

        // Create a paginator
        $pageLoader = static function (): \Generator {
            yield [1, 2, 3, 4, 5];
            yield [6, 7, 8, 9, 10];
        };

        $paginator = Paginator::createFromGenerator($pageLoader(), null);

        // Create collection with paginator
        $collection = $testCollection::create($paginator);

        // Apply multiple filters
        $filtered = $collection
            ->filter(static fn($item) => $item > 3)
            ->filter(static fn($item) => $item < 9)
            ->filter(static fn($item) => $item % 2 === 0);

        // Check result
        Assert::equals($filtered->toArray(), [4, 6, 8]);
    }

    #[Test]
    public function first(): void
    {
        // Create a test collection class
        $testCollection = new class([]) extends Collection {};

        // Create a paginator that will verify lazy loading
        $pagesLoaded = [0 => false, 1 => false, 2 => false];
        $f = static fn(int $num): object => (object) ['num' => $num];
        $pageLoader = static function () use (&$pagesLoaded, $f): \Generator {
            $pagesLoaded[0] = true;
            yield [$f(0)];

            $pagesLoaded[1] = true;
            yield [$f(1), $f(2), $f(3), $f(4), $f(5)];

            $pagesLoaded[2] = true;
            yield [$f(6), $f(7), $f(8), $f(9), $f(10)];
        };

        $paginator = Paginator::createFromGenerator($pageLoader(), null);
        // It always starts the generator when the closure is called
        Assert::true($pagesLoaded[0]);

        // Create collection with paginator
        $collection = $testCollection::create($paginator);

        // Apply filter that will only match items on the 2nd page
        $filtered = $collection->filter(static fn($item) => $item->num < 7);

        // At this point, no pages should be loaded
        Assert::false($pagesLoaded[1]);
        Assert::false($pagesLoaded[2]);

        // Get first matching item
        $first = $filtered->first();
        Assert::same($first->num, 0);
        // We got '0' from the 0-page, so we need to load the next pages
        Assert::false($pagesLoaded[1]);
        Assert::false($pagesLoaded[2]);

        // Now the 1st page should be loaded to get "1" value
        $first = $filtered->first(static fn($item) => $item->num > 0);
        Assert::same($first->num, 1);
        Assert::true($pagesLoaded[1]);
        Assert::false($pagesLoaded[2]);
    }

    #[DataProvider('provideLimitCases')]
    #[Test]
    public function limit(array $sourceItems, int $limit, array $expectedItems): void
    {
        $testCollection = new class([]) extends Collection {};
        $collection = $testCollection::create($sourceItems);

        $limited = $collection->limit($limit);

        Assert::equals($limited->toArray(), $expectedItems);

        // Check count matches expected
        Assert::count($limited, \count($expectedItems));
    }

    #[Test]
    public function limitWithFilter(): void
    {
        $testCollection = new class([]) extends Collection {};
        $collection = $testCollection::create([1, 2, 3, 4, 5, 6, 7, 8, 9, 10]);

        $filtered = $collection
            ->filter(static fn($item) => $item > 3)
            ->limit(2);

        Assert::equals($filtered->toArray(), [4, 5]);
        Assert::count($filtered, 2);
    }

    #[Test]
    public function limitWithPaginator(): void
    {
        $testCollection = new class([]) extends Collection {};

        $pageLoader = static function (): \Generator {
            yield [1, 2, 3];
            yield [4, 5, 6];
            yield [7, 8, 9];
        };

        $paginator = Paginator::createFromGenerator($pageLoader(), null);
        $collection = $testCollection::create($paginator);

        $limited = $collection->limit(4);

        Assert::equals($limited->toArray(), [1, 2, 3, 4]);
        Assert::count($limited, 4);
    }

    #[Test]
    public function limitWithZeroCountResetsLimit(): void
    {
        $testCollection = new class([]) extends Collection {};
        $collection = $testCollection::create([1, 2, 3, 4, 5]);
        $limitedCollection = $collection->limit(2);

        // Verify the limit was applied
        Assert::equals($limitedCollection->toArray(), [1, 2]);

        // Act - apply zero limit to reset the limit
        $resetCollection = $limitedCollection->limit(0);

        // Assert - should have all items
        Assert::equals($resetCollection->toArray(), [1, 2, 3, 4, 5]);
    }

    #[Test]
    public function limitResetWithFilteredCollection(): void
    {
        $testCollection = new class([]) extends Collection {};
        $collection = $testCollection::create([1, 2, 3, 4, 5, 6, 7, 8, 9, 10]);

        // Apply filter and limit
        $filtered = $collection
            ->filter(static fn($item) => $item > 3)
            ->limit(2);

        // Verify initial state
        Assert::equals($filtered->toArray(), [4, 5]);

        // Act - reset limit
        $resetLimited = $filtered->limit(0);

        // Assert - filter should still be applied, but not the limit
        Assert::equals($resetLimited->toArray(), [4, 5, 6, 7, 8, 9, 10]);
    }

    #[Test]
    public function countWithLimit(): void
    {
        $testCollection = new class([]) extends Collection {};
        $collection = $testCollection::create([1, 2, 3, 4, 5, 6, 7, 8, 9, 10]);

        $limited = $collection->limit(3);

        Assert::count($limited, 3);
    }

    #[Test]
    public function emptyWithLimit(): void
    {
        $testCollection = new class([]) extends Collection {};
        $collection = $testCollection::create([1, 2, 3]);

        Assert::false($collection->limit(1)->empty());
        Assert::false($collection->limit(0)->empty());

        // With an empty source collection
        $emptyCollection = $testCollection::create([]);
        Assert::true($emptyCollection->limit(5)->empty());
    }
}
