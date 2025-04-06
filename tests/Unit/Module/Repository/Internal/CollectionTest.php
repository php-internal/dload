<?php

declare(strict_types=1);

namespace Internal\DLoad\Tests\Unit\Module\Repository\Internal;

use Internal\DLoad\Module\Repository\Internal\Collection;
use Internal\DLoad\Module\Repository\Internal\Paginator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Collection::class)]
final class CollectionTest extends TestCase
{
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
}
