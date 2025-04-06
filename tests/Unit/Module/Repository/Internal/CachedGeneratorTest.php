<?php

declare(strict_types=1);

namespace Internal\DLoad\Tests\Unit\Module\Repository\Internal;

use Generator;
use Internal\DLoad\Module\Repository\Internal\CachedGenerator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(CachedGenerator::class)]
final class CachedGeneratorTest extends TestCase
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
        self::assertCount(3, $iteratedItems);
        self::assertSame([0, 1, 2], $iteratedItems);
        // self::assertCount(5, $allItems);
        self::assertSame([0, 1, 2, 3, 4], $allItems);
    }

    /**
     * Tests that first() returns the first element from the generator.
     */
    public function testFirstReturnsFirstElement(): void
    {
        // Arrange
        $generator = $this->createGenerator(3);
        $cachedGenerator = new CachedGenerator($generator);

        // Act
        $firstItem = $cachedGenerator->first();

        // Assert
        self::assertSame(0, $firstItem);
    }

    /**
     * Tests that first() returns null when the generator is empty.
     */
    public function testFirstReturnsNullForEmptyGenerator(): void
    {
        // Arrange
        $generator = $this->createGenerator(0);
        $cachedGenerator = new CachedGenerator($generator);

        // Act
        $firstItem = $cachedGenerator->first();

        // Assert
        self::assertNull($firstItem);
    }

    /**
     * Tests that first() can retrieve the first item without affecting future iterations.
     */
    public function testFirstDoesNotConsumeItemFromIteration(): void
    {
        // Arrange
        $generator = $this->createGenerator(3);
        $cachedGenerator = new CachedGenerator($generator);

        // Act
        $firstItem = $cachedGenerator->first();
        $allItems = \iterator_to_array($cachedGenerator);

        // Assert
        self::assertSame(0, $firstItem);
        self::assertCount(3, $allItems);
        self::assertSame([0, 1, 2], $allItems);
    }

    /**
     * Tests that isEmpty() correctly identifies empty generators.
     */
    public function testIsEmptyReturnsTrueForEmptyGenerator(): void
    {
        // Arrange
        $generator = $this->createGenerator(0);
        $cachedGenerator = new CachedGenerator($generator);

        // Act
        $isEmpty = $cachedGenerator->isEmpty();

        // Assert
        self::assertTrue($isEmpty);
    }

    /**
     * Tests that isEmpty() correctly identifies non-empty generators.
     */
    public function testIsEmptyReturnsFalseForNonEmptyGenerator(): void
    {
        // Arrange
        $generator = $this->createGenerator(1);
        $cachedGenerator = new CachedGenerator($generator);

        // Act
        $isEmpty = $cachedGenerator->isEmpty();

        // Assert
        self::assertFalse($isEmpty);
    }

    /**
     * Tests that count() correctly returns the number of items in the generator.
     */
    public function testCountReturnsCorrectItemCount(): void
    {
        // Arrange
        $generator = $this->createGenerator(5);
        $cachedGenerator = new CachedGenerator($generator);

        // Act
        $count = $cachedGenerator->count();

        // Assert
        self::assertSame(5, $count);
    }

    /**
     * Tests that count() returns zero for an empty generator.
     */
    public function testCountReturnsZeroForEmptyGenerator(): void
    {
        // Arrange
        $generator = $this->createGenerator(0);
        $cachedGenerator = new CachedGenerator($generator);

        // Act
        $count = $cachedGenerator->count();

        // Assert
        self::assertSame(0, $count);
    }

    /**
     * Tests that partial iteration followed by count() returns the correct total count.
     */
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
        self::assertSame(5, $count);
    }

    /**
     * Tests that the cache persists after a complete iteration.
     */
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
        self::assertSame($firstIteration, $secondIteration);
        self::assertSame([0, 1, 2], $firstIteration);
    }

    /**
     * Tests that the cached generator correctly handles various traversable types.
     *
     * @param \Traversable $traversable The traversable to test
     * @param array $expected The expected result
     */
    #[DataProvider('provideTraversables')]
    public function testHandlesVariousTraversableTypes(\Traversable $traversable, array $expected): void
    {
        // Arrange
        $cachedGenerator = new CachedGenerator($traversable);

        // Act
        $result = \iterator_to_array($cachedGenerator);

        // Assert
        self::assertSame($expected, $result);
    }

    /**
     * Tests that cached generator works correctly with nested generators.
     */
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
        self::assertSame([0, 1, 10, 11], $result);
    }

    /**
     * Tests behavior with large datasets to ensure memory effectiveness.
     */
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
        self::assertSame(0, $firstAccess);
        self::assertCount(10, $partialIteration);
        self::assertSame(1000, $totalCount);
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
