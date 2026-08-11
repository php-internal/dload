<?php

declare(strict_types=1);

namespace Internal\DLoad\Tests\Unit\Module\Repository\Internal;

use Internal\DLoad\Module\Repository\Internal\Collection;
use Internal\DLoad\Module\Repository\Internal\Paginator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[\Testo\Codecov\Covers(Collection::class)]
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

    #[\Testo\Test]
    public function testCollectionWithIterable(): void
    {
        // Create a test collection class
        $testCollection = new class([]) extends Collection {};

        // Test with array
        $arrayCollection = $testCollection::create(['item1', 'item2', 'item3']);
        \Testo\Assert::equals($arrayCollection->toArray(), ['item1', 'item2', 'item3']);

        // Test with generator
        $generator = static function () {
            yield 'gen1';
            yield 'gen2';
        };

        $generatorCollection = $testCollection::create($generator());
        \Testo\Assert::equals($generatorCollection->toArray(), ['gen1', 'gen2']);
    }

    #[\Testo\Test]
    public function testCollectionWithPaginator(): void
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
        \Testo\Assert::equals($collection->toArray(), ['page1-item1', 'page1-item2', 'page2-item1', 'page2-item2']);
    }

    #[\Testo\Test]
    public function testFilterChaining(): void
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
        \Testo\Assert::equals($filtered->toArray(), [4, 6]);
    }

    #[\Testo\Test]
    public function testFilterWithPaginator(): void
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
        \Testo\Assert::equals($filtered->toArray(), [4, 6, 8]);
    }

    #[\Testo\Test]
    public function testFirst(): void
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
        \Testo\Assert::true($pagesLoaded[0]);

        // Create collection with paginator
        $collection = $testCollection::create($paginator);

        // Apply filter that will only match items on the 2nd page
        $filtered = $collection->filter(static fn($item) => $item->num < 7);

        // At this point, no pages should be loaded
        \Testo\Assert::false($pagesLoaded[1]);
        \Testo\Assert::false($pagesLoaded[2]);

        // Get first matching item
        $first = $filtered->first();
        \Testo\Assert::same($first->num, 0);
        // We got '0' from the 0-page, so we need to load the next pages
        \Testo\Assert::false($pagesLoaded[1]);
        \Testo\Assert::false($pagesLoaded[2]);


        // Now the 1st page should be loaded to get "1" value
        $first = $filtered->first(static fn($item) => $item->num > 0);
        \Testo\Assert::same($first->num, 1);
        \Testo\Assert::true($pagesLoaded[1]);
        \Testo\Assert::false($pagesLoaded[2]);
    }

    #[\Testo\Data\DataProvider('provideLimitCases')]
    #[\Testo\Test]
    public function testLimit(array $sourceItems, int $limit, array $expectedItems): void
    {
        // Arrange
        $testCollection = new class([]) extends Collection {};
        $collection = $testCollection::create($sourceItems);

        // Act
        $limited = $collection->limit($limit);

        // Assert
        \Testo\Assert::equals($limited->toArray(), $expectedItems);

        // Check count matches expected
        \Testo\Assert::count($limited, \count($expectedItems));
    }

    #[\Testo\Test]
    public function testLimitWithFilter(): void
    {
        // Arrange
        $testCollection = new class([]) extends Collection {};
        $collection = $testCollection::create([1, 2, 3, 4, 5, 6, 7, 8, 9, 10]);

        // Act
        $filtered = $collection
            ->filter(static fn($item) => $item > 3)
            ->limit(2);

        // Assert
        \Testo\Assert::equals($filtered->toArray(), [4, 5]);
        \Testo\Assert::count($filtered, 2);
    }

    #[\Testo\Test]
    public function testLimitWithPaginator(): void
    {
        // Arrange
        $testCollection = new class([]) extends Collection {};

        $pageLoader = static function (): \Generator {
            yield [1, 2, 3];
            yield [4, 5, 6];
            yield [7, 8, 9];
        };

        $paginator = Paginator::createFromGenerator($pageLoader(), null);
        $collection = $testCollection::create($paginator);

        // Act
        $limited = $collection->limit(4);

        // Assert
        \Testo\Assert::equals($limited->toArray(), [1, 2, 3, 4]);
        \Testo\Assert::count($limited, 4);
    }

    #[\Testo\Test]
    public function testLimitWithZeroCountResetsLimit(): void
    {
        // Arrange
        $testCollection = new class([]) extends Collection {};
        $collection = $testCollection::create([1, 2, 3, 4, 5]);
        $limitedCollection = $collection->limit(2);

        // Verify the limit was applied
        \Testo\Assert::equals($limitedCollection->toArray(), [1, 2]);

        // Act - apply zero limit to reset the limit
        $resetCollection = $limitedCollection->limit(0);

        // Assert - should have all items
        \Testo\Assert::equals($resetCollection->toArray(), [1, 2, 3, 4, 5]);
    }

    #[\Testo\Test]
    public function testLimitResetWithFilteredCollection(): void
    {
        // Arrange
        $testCollection = new class([]) extends Collection {};
        $collection = $testCollection::create([1, 2, 3, 4, 5, 6, 7, 8, 9, 10]);

        // Apply filter and limit
        $filtered = $collection
            ->filter(static fn($item) => $item > 3)
            ->limit(2);

        // Verify initial state
        \Testo\Assert::equals($filtered->toArray(), [4, 5]);

        // Act - reset limit
        $resetLimited = $filtered->limit(0);

        // Assert - filter should still be applied, but not the limit
        \Testo\Assert::equals($resetLimited->toArray(), [4, 5, 6, 7, 8, 9, 10]);
    }

    #[\Testo\Test]
    public function testCountWithLimit(): void
    {
        // Arrange
        $testCollection = new class([]) extends Collection {};
        $collection = $testCollection::create([1, 2, 3, 4, 5, 6, 7, 8, 9, 10]);

        // Act
        $limited = $collection->limit(3);

        // Assert
        \Testo\Assert::count($limited, 3);
    }

    #[\Testo\Test]
    public function testEmptyWithLimit(): void
    {
        // Arrange
        $testCollection = new class([]) extends Collection {};
        $collection = $testCollection::create([1, 2, 3]);

        // Act & Assert
        \Testo\Assert::false($collection->limit(1)->empty());
        \Testo\Assert::false($collection->limit(0)->empty());

        // With an empty source collection
        $emptyCollection = $testCollection::create([]);
        \Testo\Assert::true($emptyCollection->limit(5)->empty());
    }
}
