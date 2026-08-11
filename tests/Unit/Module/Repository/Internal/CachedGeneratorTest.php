<?php

declare(strict_types=1);

namespace Internal\DLoad\Tests\Unit\Module\Repository\Internal;

use Generator;
use Internal\DLoad\Module\Repository\Internal\CachedGenerator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[\Testo\Codecov\Covers(CachedGenerator::class)]
final class CachedGeneratorTest
{
    /**
     * Data provider for traversable types test.
     */
    public static function provideTraversables(): \Generator
    {
        yield 'generator' => [
            (static function () {
                yield 'a';
                yield 'b';
                yield 'c';
            })(),
            ['a', 'b', 'c'],
        ];

        yield 'array iterator' => [
            new \ArrayIterator(['x', 'y', 'z']),
            ['x', 'y', 'z'],
        ];
    }

    /**
     * Tests that the generator correctly caches items as they are yielded.
     */
    #[\Testo\Test]
    public function testIterationCachesYieldedItems(): void
    {
        // Arrange
        $generator = $this->createGenerator(5);
        $cachedGenerator = new CachedGenerator($generator);

        // Act - Partially iterate through generator
        $i = 0;
        $iteratedItems = [];
        foreach ($cachedGenerator->getIterator() as $item) {
            $iteratedItems[] = $item;
            // Only consume first 3 items
            if (++$i >= 3) {
                break;
            }
        }

        // Get all items using a new iteration
        $allItems = \iterator_to_array($cachedGenerator);

        // Assert
        \Testo\Assert::count($iteratedItems, 3);
        \Testo\Assert::same($iteratedItems, [0, 1, 2]);
        // self::assertCount(5, $allItems);
        \Testo\Assert::same($allItems, [0, 1, 2, 3, 4]);
    }

    /**
     * Tests that first() returns the first element from the generator.
     */
    #[\Testo\Test]
    public function testFirstReturnsFirstElement(): void
    {
        // Arrange
        $generator = $this->createGenerator(3);
        $cachedGenerator = new CachedGenerator($generator);

        // Act
        $firstItem = $cachedGenerator->first();

        // Assert
        \Testo\Assert::same($firstItem, 0);
    }

    /**
     * Tests that first() returns null when the generator is empty.
     */
    #[\Testo\Test]
    public function testFirstReturnsNullForEmptyGenerator(): void
    {
        // Arrange
        $generator = $this->createGenerator(0);
        $cachedGenerator = new CachedGenerator($generator);

        // Act
        $firstItem = $cachedGenerator->first();

        // Assert
        \Testo\Assert::null($firstItem);
    }

    /**
     * Tests that first() can retrieve the first item without affecting future iterations.
     */
    #[\Testo\Test]
    public function testFirstDoesNotConsumeItemFromIteration(): void
    {
        // Arrange
        $generator = $this->createGenerator(3);
        $cachedGenerator = new CachedGenerator($generator);

        // Act
        $firstItem = $cachedGenerator->first();
        $allItems = \iterator_to_array($cachedGenerator);

        // Assert
        \Testo\Assert::same($firstItem, 0);
        \Testo\Assert::count($allItems, 3);
        \Testo\Assert::same($allItems, [0, 1, 2]);
    }

    /**
     * Tests that isEmpty() correctly identifies empty generators.
     */
    #[\Testo\Test]
    public function testIsEmptyReturnsTrueForEmptyGenerator(): void
    {
        // Arrange
        $generator = $this->createGenerator(0);
        $cachedGenerator = new CachedGenerator($generator);

        // Act
        $isEmpty = $cachedGenerator->isEmpty();

        // Assert
        \Testo\Assert::true($isEmpty);
    }

    /**
     * Tests that isEmpty() correctly identifies non-empty generators.
     */
    #[\Testo\Test]
    public function testIsEmptyReturnsFalseForNonEmptyGenerator(): void
    {
        // Arrange
        $generator = $this->createGenerator(1);
        $cachedGenerator = new CachedGenerator($generator);

        // Act
        $isEmpty = $cachedGenerator->isEmpty();

        // Assert
        \Testo\Assert::false($isEmpty);
    }

    /**
     * Tests that count() correctly returns the number of items in the generator.
     */
    #[\Testo\Test]
    public function testCountReturnsCorrectItemCount(): void
    {
        // Arrange
        $generator = $this->createGenerator(5);
        $cachedGenerator = new CachedGenerator($generator);

        // Act
        $count = $cachedGenerator->count();

        // Assert
        \Testo\Assert::same($count, 5);
    }

    /**
     * Tests that count() returns zero for an empty generator.
     */
    #[\Testo\Test]
    public function testCountReturnsZeroForEmptyGenerator(): void
    {
        // Arrange
        $generator = $this->createGenerator(0);
        $cachedGenerator = new CachedGenerator($generator);

        // Act
        $count = $cachedGenerator->count();

        // Assert
        \Testo\Assert::same($count, 0);
    }

    /**
     * Tests that partial iteration followed by count() returns the correct total count.
     */
    #[\Testo\Test]
    public function testPartialIterationFollowedByCountReturnsCorrectTotal(): void
    {
        // Arrange
        $generator = $this->createGenerator(5);
        $cachedGenerator = new CachedGenerator($generator);

        // Act - Partially iterate
        $i = 0;
        foreach ($cachedGenerator as $item) {
            if (++$i >= 2) {
                break;
            }
        }

        // Get count
        $count = $cachedGenerator->count();

        // Assert
        \Testo\Assert::same($count, 5);
    }

    /**
     * Tests that the cache persists after a complete iteration.
     */
    #[\Testo\Test]
    public function testCachePersistsAfterCompleteIteration(): void
    {
        // Arrange
        $generator = $this->createGenerator(3);
        $cachedGenerator = new CachedGenerator($generator);

        // Act - First iteration
        $firstIteration = \iterator_to_array($cachedGenerator);

        // Second iteration should use cache
        $secondIteration = \iterator_to_array($cachedGenerator);

        // Assert
        \Testo\Assert::same($secondIteration, $firstIteration);
        \Testo\Assert::same($firstIteration, [0, 1, 2]);
    }

    /**
     * Tests that the cached generator correctly handles various traversable types.
     *
     * @param \Traversable $traversable The traversable to test
     * @param array $expected The expected result
     */
    #[\Testo\Data\DataProvider('provideTraversables')]
    #[\Testo\Test]
    public function testHandlesVariousTraversableTypes(\Traversable $traversable, array $expected): void
    {
        // Arrange
        $cachedGenerator = new CachedGenerator($traversable);

        // Act
        $result = \iterator_to_array($cachedGenerator);

        // Assert
        \Testo\Assert::same($result, $expected);
    }

    /**
     * Tests that cached generator works correctly with nested generators.
     */
    #[\Testo\Test]
    public function testHandlesNestedGenerators(): void
    {
        // Arrange
        $nestedGenerator = function () {
            yield from $this->createGenerator(2);
            yield from $this->createGenerator(2, 10);
        };

        $cachedGenerator = new CachedGenerator($nestedGenerator());

        // Act
        $result = \iterator_to_array($cachedGenerator);

        // Assert
        \Testo\Assert::same($result, [0, 1, 10, 11]);
    }

    /**
     * Tests behavior with large datasets to ensure memory effectiveness.
     */
    #[\Testo\Test]
    public function testHandlesLargeDatasets(): void
    {
        // Arrange
        $largeGenerator = $this->createGenerator(1000);
        $cachedGenerator = new CachedGenerator($largeGenerator);

        // Act
        $firstAccess = $cachedGenerator->first();

        // Partially iterate
        $partialIteration = [];
        $i = 0;
        foreach ($cachedGenerator as $item) {
            $partialIteration[] = $item;
            if (++$i >= 10) {
                break;
            }
        }

        // Get count without completing iteration
        $totalCount = $cachedGenerator->count();

        // Assert
        \Testo\Assert::same($firstAccess, 0);
        \Testo\Assert::count($partialIteration, 10);
        \Testo\Assert::same($totalCount, 1000);
    }

    /**
     * Creates a simple generator that yields consecutive integers.
     *
     * @param int $count Number of items to yield
     * @param int $start Starting value
     */
    private function createGenerator(int $count, int $start = 0): \Generator
    {
        for ($i = $start; $i < $start + $count; $i++) {
            yield $i;
        }
    }
}
