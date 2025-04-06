<?php

declare(strict_types=1);

namespace Internal\DLoad\Tests\Unit\Module\Repository\Internal;

use Internal\DLoad\Module\Repository\Internal\Collection;
use Internal\DLoad\Module\Repository\Internal\Paginator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Collection::class)]
final class CollectionTest extends TestCase
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

    public function testCollectionWithIterable(): void
    {
        // Create a test collection class
        $testCollection = new class([]) extends Collection {};

        // Test with array
        $arrayCollection = $testCollection::create(['item1', 'item2', 'item3']);
        $this->assertEquals(['item1', 'item2', 'item3'], $arrayCollection->toArray());

        // Test with generator
        $generator = static function () {
            yield 'gen1';
            yield 'gen2';
        };

        $generatorCollection = $testCollection::create($generator());
        $this->assertEquals(['gen1', 'gen2'], $generatorCollection->toArray());
    }

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
        $this->assertEquals(
            ['page1-item1', 'page1-item2', 'page2-item1', 'page2-item2'],
            $collection->toArray(),
        );
    }

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
        $this->assertEquals([4, 6], $filtered->toArray());
    }

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
        $this->assertEquals([4, 6, 8], $filtered->toArray());
    }

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
        $this->assertTrue($pagesLoaded[0]);

        // Create collection with paginator
        $collection = $testCollection::create($paginator);

        // Apply filter that will only match items on the 2nd page
        $filtered = $collection->filter(static fn($item) => $item->num < 7);

        // At this point, no pages should be loaded
        $this->assertFalse($pagesLoaded[1]);
        $this->assertFalse($pagesLoaded[2]);

        // Get first matching item
        $first = $filtered->first();
        self::assertSame(0, $first->num);
        // We got '0' from the 0-page, so we need to load the next pages
        $this->assertFalse($pagesLoaded[1]);
        $this->assertFalse($pagesLoaded[2]);


        // Now the 1st page should be loaded to get "1" value
        $first = $filtered->first(static fn($item) => $item->num > 0);
        self::assertSame(1, $first->num);
        $this->assertTrue($pagesLoaded[1]);
        $this->assertFalse($pagesLoaded[2]);
    }

    #[DataProvider('provideLimitCases')]
    public function testLimit(array $sourceItems, int $limit, array $expectedItems): void
    {
        // Arrange
        $testCollection = new class([]) extends Collection {};
        $collection = $testCollection::create($sourceItems);

        // Act
        $limited = $collection->limit($limit);

        // Assert
        self::assertEquals($expectedItems, $limited->toArray());

        // Check count matches expected
        self::assertCount(\count($expectedItems), $limited);
    }

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
        self::assertEquals([4, 5], $filtered->toArray());
        self::assertCount(2, $filtered);
    }

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
        self::assertEquals([1, 2, 3, 4], $limited->toArray());
        self::assertCount(4, $limited);
    }

    public function testLimitWithZeroCountResetsLimit(): void
    {
        // Arrange
        $testCollection = new class([]) extends Collection {};
        $collection = $testCollection::create([1, 2, 3, 4, 5]);
        $limitedCollection = $collection->limit(2);

        // Verify the limit was applied
        self::assertEquals([1, 2], $limitedCollection->toArray());

        // Act - apply zero limit to reset the limit
        $resetCollection = $limitedCollection->limit(0);

        // Assert - should have all items
        self::assertEquals([1, 2, 3, 4, 5], $resetCollection->toArray());
    }

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
        self::assertEquals([4, 5], $filtered->toArray());

        // Act - reset limit
        $resetLimited = $filtered->limit(0);

        // Assert - filter should still be applied, but not the limit
        self::assertEquals([4, 5, 6, 7, 8, 9, 10], $resetLimited->toArray());
    }

    public function testCountWithLimit(): void
    {
        // Arrange
        $testCollection = new class([]) extends Collection {};
        $collection = $testCollection::create([1, 2, 3, 4, 5, 6, 7, 8, 9, 10]);

        // Act
        $limited = $collection->limit(3);

        // Assert
        self::assertCount(3, $limited);
    }

    public function testEmptyWithLimit(): void
    {
        // Arrange
        $testCollection = new class([]) extends Collection {};
        $collection = $testCollection::create([1, 2, 3]);

        // Act & Assert
        self::assertFalse($collection->limit(1)->empty());
        self::assertFalse($collection->limit(0)->empty());

        // With an empty source collection
        $emptyCollection = $testCollection::create([]);
        self::assertTrue($emptyCollection->limit(5)->empty());
    }
}
