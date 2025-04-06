<?php

declare(strict_types=1);

namespace Internal\DLoad\Tests\Module\Repository\Internal;

use Internal\DLoad\Module\Repository\Internal\Paginator;
use PHPUnit\Framework\TestCase;

final class PaginatorTest extends TestCase
{
    public function testCreateFromGenerator(): void
    {
        // Create a generator that yields arrays of items for each page
        $loader = static function (): \Generator {
            yield ['Item 1', 'Item 2']; // Page 1
            yield ['Item 3', 'Item 4']; // Page 2
            yield ['Item 5']; // Page 3
        };

        $paginator = Paginator::createFromGenerator($loader(), null);

        // Test that we get the first page items
        $this->assertEquals(['Item 1', 'Item 2'], $paginator->getPageItems());
        $this->assertEquals(1, $paginator->getPageNumber());

        // Test getting next page
        $page2 = $paginator->getNextPage();
        $this->assertNotNull($page2);
        $this->assertEquals(['Item 3', 'Item 4'], $page2->getPageItems());
        $this->assertEquals(2, $page2->getPageNumber());

        // Test getting third page
        $page3 = $page2->getNextPage();
        $this->assertNotNull($page3);
        $this->assertEquals(['Item 5'], $page3->getPageItems());
        $this->assertEquals(3, $page3->getPageNumber());

        // Test that there's no fourth page
        $page4 = $page3->getNextPage();
        $this->assertNull($page4);
    }

    public function testIteration(): void
    {
        // Create a generator that yields arrays of items for each page
        $loader = static function (): \Generator {
            yield ['Item 1', 'Item 2']; // Page 1
            yield ['Item 3', 'Item 4']; // Page 2
        };

        $paginator = Paginator::createFromGenerator($loader(), null);

        // Test iterating through all items
        $items = \iterator_to_array($paginator);
        $this->assertEquals(['Item 1', 'Item 2', 'Item 3', 'Item 4'], $items);
    }

    public function testCountWithCounter(): void
    {
        // Create a generator
        $loader = static function (): \Generator {
            yield ['Item 1', 'Item 2']; // Page 1
            yield ['Item 3', 'Item 4']; // Page 2
        };

        // Create a counter function
        $counter = static fn() => 4; // Total number of items

        $paginator = Paginator::createFromGenerator($loader(), $counter);

        // Test count()
        $this->assertEquals(4, $paginator->count());
    }

    public function testCountWithoutCounter(): void
    {
        // Create a generator
        $loader = static function (): \Generator {
            yield ['Item 1', 'Item 2']; // Page 1
        };

        $paginator = Paginator::createFromGenerator($loader(), null);

        // Test that count() throws an exception when no counter is provided
        $this->expectException(\LogicException::class);
        $paginator->count();
    }
}
