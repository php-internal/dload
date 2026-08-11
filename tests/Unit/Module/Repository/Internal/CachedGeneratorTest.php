<?php

declare(strict_types=1);

namespace Internal\DLoad\Tests\Unit\Module\Repository\Internal;

use Generator;
use Internal\DLoad\Module\Repository\Internal\CachedGenerator;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Data\DataProvider;
use Testo\Test;

#[Covers(CachedGenerator::class)]
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
    #[Test]
    public function iterationCachesYieldedItems(): void
    {
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

        Assert::count($iteratedItems, 3);
        Assert::same($iteratedItems, [0, 1, 2]);
        // self::assertCount(5, $allItems);
        Assert::same($allItems, [0, 1, 2, 3, 4]);
    }

    /**
     * Tests that first() returns the first element from the generator.
     */
    #[Test]
    public function firstReturnsFirstElement(): void
    {
        $generator = $this->createGenerator(3);
        $cachedGenerator = new CachedGenerator($generator);

        $firstItem = $cachedGenerator->first();

        Assert::same($firstItem, 0);
    }

    /**
     * Tests that first() returns null when the generator is empty.
     */
    #[Test]
    public function firstReturnsNullForEmptyGenerator(): void
    {
        $generator = $this->createGenerator(0);
        $cachedGenerator = new CachedGenerator($generator);

        $firstItem = $cachedGenerator->first();

        Assert::null($firstItem);
    }

    /**
     * Tests that first() can retrieve the first item without affecting future iterations.
     */
    #[Test]
    public function firstDoesNotConsumeItemFromIteration(): void
    {
        $generator = $this->createGenerator(3);
        $cachedGenerator = new CachedGenerator($generator);

        $firstItem = $cachedGenerator->first();
        $allItems = \iterator_to_array($cachedGenerator);

        Assert::same($firstItem, 0);
        Assert::count($allItems, 3);
        Assert::same($allItems, [0, 1, 2]);
    }

    /**
     * Tests that isEmpty() correctly identifies empty generators.
     */
    #[Test]
    public function isEmptyReturnsTrueForEmptyGenerator(): void
    {
        $generator = $this->createGenerator(0);
        $cachedGenerator = new CachedGenerator($generator);

        $isEmpty = $cachedGenerator->isEmpty();

        Assert::true($isEmpty);
    }

    /**
     * Tests that isEmpty() correctly identifies non-empty generators.
     */
    #[Test]
    public function isEmptyReturnsFalseForNonEmptyGenerator(): void
    {
        $generator = $this->createGenerator(1);
        $cachedGenerator = new CachedGenerator($generator);

        $isEmpty = $cachedGenerator->isEmpty();

        Assert::false($isEmpty);
    }

    /**
     * Tests that count() correctly returns the number of items in the generator.
     */
    #[Test]
    public function countReturnsCorrectItemCount(): void
    {
        $generator = $this->createGenerator(5);
        $cachedGenerator = new CachedGenerator($generator);

        $count = $cachedGenerator->count();

        Assert::same($count, 5);
    }

    /**
     * Tests that count() returns zero for an empty generator.
     */
    #[Test]
    public function countReturnsZeroForEmptyGenerator(): void
    {
        $generator = $this->createGenerator(0);
        $cachedGenerator = new CachedGenerator($generator);

        $count = $cachedGenerator->count();

        Assert::same($count, 0);
    }

    /**
     * Tests that partial iteration followed by count() returns the correct total count.
     */
    #[Test]
    public function partialIterationFollowedByCountReturnsCorrectTotal(): void
    {
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

        Assert::same($count, 5);
    }

    /**
     * Tests that the cache persists after a complete iteration.
     */
    #[Test]
    public function cachePersistsAfterCompleteIteration(): void
    {
        $generator = $this->createGenerator(3);
        $cachedGenerator = new CachedGenerator($generator);

        // Act - First iteration
        $firstIteration = \iterator_to_array($cachedGenerator);

        // Second iteration should use cache
        $secondIteration = \iterator_to_array($cachedGenerator);

        Assert::same($secondIteration, $firstIteration);
        Assert::same($firstIteration, [0, 1, 2]);
    }

    /**
     * Tests that the cached generator correctly handles various traversable types.
     *
     * @param \Traversable $traversable The traversable to test
     * @param array $expected The expected result
     */
    #[DataProvider('provideTraversables')]
    #[Test]
    public function handlesVariousTraversableTypes(\Traversable $traversable, array $expected): void
    {
        $cachedGenerator = new CachedGenerator($traversable);

        $result = \iterator_to_array($cachedGenerator);

        Assert::same($result, $expected);
    }

    /**
     * Tests that cached generator works correctly with nested generators.
     */
    #[Test]
    public function handlesNestedGenerators(): void
    {
        $nestedGenerator = function () {
            yield from $this->createGenerator(2);
            yield from $this->createGenerator(2, 10);
        };

        $cachedGenerator = new CachedGenerator($nestedGenerator());

        $result = \iterator_to_array($cachedGenerator);

        Assert::same($result, [0, 1, 10, 11]);
    }

    /**
     * Tests behavior with large datasets to ensure memory effectiveness.
     */
    #[Test]
    public function handlesLargeDatasets(): void
    {
        $largeGenerator = $this->createGenerator(1000);
        $cachedGenerator = new CachedGenerator($largeGenerator);

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

        Assert::same($firstAccess, 0);
        Assert::count($partialIteration, 10);
        Assert::same($totalCount, 1000);
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
