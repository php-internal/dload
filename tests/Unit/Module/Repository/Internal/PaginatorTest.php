<?php

declare(strict_types=1);

namespace Internal\DLoad\Tests\Unit\Module\Repository\Internal;

use Internal\DLoad\Module\Repository\Internal\Paginator;
use PHPUnit\Framework\TestCase;

final class PaginatorTest
{
    #[\Testo\Test]
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
        \Testo\Assert::equals($paginator->getPageItems(), ['Item 1', 'Item 2']);
        \Testo\Assert::equals($paginator->getPageNumber(), 1);

        // Test getting next page
        $page2 = $paginator->getNextPage();
        \Testo\Assert::notNull($page2);
        \Testo\Assert::equals($page2->getPageItems(), ['Item 3', 'Item 4']);
        \Testo\Assert::equals($page2->getPageNumber(), 2);

        // Test getting third page
        $page3 = $page2->getNextPage();
        \Testo\Assert::notNull($page3);
        \Testo\Assert::equals($page3->getPageItems(), ['Item 5']);
        \Testo\Assert::equals($page3->getPageNumber(), 3);

        // Test that there's no fourth page
        $page4 = $page3->getNextPage();
        \Testo\Assert::null($page4);
    }

    #[\Testo\Test]
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
        \Testo\Assert::equals($items, ['Item 1', 'Item 2', 'Item 3', 'Item 4']);
    }

    #[\Testo\Test]
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
        \Testo\Assert::equals($paginator->count(), 4);
    }

    #[\Testo\Test]
    public function testCountWithoutCounter(): void
    {
        // Create a generator
        $loader = static function (): \Generator {
            yield ['Item 1', 'Item 2']; // Page 1
        };

        $paginator = Paginator::createFromGenerator($loader(), null);

        // Test that count() throws an exception when no counter is provided
        \Testo\Expect::exception(\LogicException::class);
        $paginator->count();
    }
}
