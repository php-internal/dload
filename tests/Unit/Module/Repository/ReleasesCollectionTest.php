<?php

declare(strict_types=1);

namespace Internal\DLoad\Tests\Unit\Module\Repository;

use Internal\DLoad\Module\Common\Architecture;
use Internal\DLoad\Module\Common\OperatingSystem;
use Internal\DLoad\Module\Common\Stability;
use Internal\DLoad\Module\Repository\Collection\ReleasesCollection;
use Internal\DLoad\Module\Repository\ReleaseInterface;
use Internal\DLoad\Module\Version\Constraint;
use Internal\DLoad\Module\Version\Version;
use Internal\DLoad\Tests\Unit\Module\Repository\Stub\AssetStub;
use Internal\DLoad\Tests\Unit\Module\Repository\Stub\ReleaseStub;
use Internal\DLoad\Tests\Unit\Module\Repository\Stub\RepositoryStub;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[\Testo\Codecov\Covers(ReleasesCollection::class)]
final class ReleasesCollectionTest
{
    private RepositoryStub $repository;

    /** @var list<ReleaseStub> */
    private array $releases;

    private ReleasesCollection $collection;

    #[\Testo\Test]
    public function testSatisfiesFiltersReleasesByVersionConstraint(): void
    {
        // Act
        $result = $this->collection->satisfies(Constraint::fromConstraintString('^1.0.0'));

        // Assert
        \Testo\Assert::count($result, 2, 'Should only include 1.0.0 and 1.5.0 versions');

        $versions = \array_map(
            static fn(ReleaseInterface $release): string => $release->getName(),
            \iterator_to_array($result),
        );

        \Testo\Assert::contains($versions, '1.0.0');
        \Testo\Assert::contains($versions, '1.5.0');
        self::assertNotContains('2.0.0', $versions);
    }

    #[\Testo\Test]
    public function testNotSatisfiesFiltersOutReleasesByVersionConstraint(): void
    {
        // Act
        $result = $this->collection->notSatisfies(Constraint::fromConstraintString('^1.0.0'));

        // Assert
        self::assertGreaterThan(0, $result->count());
        self::assertNotContains('1.0.0', $this->getVersionsFromCollection($result));
        self::assertNotContains('1.5.0', $this->getVersionsFromCollection($result));
        \Testo\Assert::contains($this->getVersionsFromCollection($result), '2.0.0');
    }

    #[\Testo\Test]
    public function testStabilityFiltersReleasesByExactStability(): void
    {
        // Act
        $result = $this->collection->stability(Stability::Beta);

        // Assert
        \Testo\Assert::count($result, 1);
        \Testo\Assert::same($result->first()->getName(), '2.1.0-beta');
    }

    #[\Testo\Test]
    public function testStableFiltersToOnlyStableReleases(): void
    {
        // Act
        $result = $this->collection->stable();

        // Assert
        \Testo\Assert::count($result, 3, 'Should only include stable versions');

        foreach ($result as $release) {
            \Testo\Assert::same($release->getVersion()->stability, Stability::Stable);
        }
    }

    #[\Testo\Test]
    public function testMinimumStabilityFiltersReleasesByMinimumStabilityLevel(): void
    {
        // Act
        $result = $this->collection->minimumStability(Stability::RC);

        // Assert
        $stabilities = [];
        foreach ($result as $release) {
            $stabilities[] = $release->getVersion()->stability;
        }

        \Testo\Assert::contains($stabilities, Stability::Stable);
        \Testo\Assert::contains($stabilities, Stability::RC);
        self::assertNotContains(Stability::Beta, $stabilities);
        self::assertNotContains(Stability::Alpha, $stabilities);
    }

    #[\Testo\Test]
    public function testSortByVersionSortsReleasesByVersionDescending(): void
    {
        // Act
        $result = $this->collection->sortByVersion();
        $versions = $this->getVersionsFromCollection($result);

        // Assert
        \Testo\Assert::same(\array_values($versions), [
            // Expected order by semantic version, newest first
            '2.1.0-beta',
            '2.1.0-alpha',
            '2.0.1-rc1',
            '2.0.0',
            '1.5.0',
            '1.0.0',
        ]);
    }

    #[\Testo\Test]
    public function testChainedFiltersWorkCorrectly(): void
    {
        // Act - Get stable releases that satisfy version constraint and sort them
        $result = $this->collection
            ->stable()
            ->satisfies(Constraint::fromConstraintString('^1.0.0'))
            ->sortByVersion();

        // Assert
        $versions = $this->getVersionsFromCollection($result);
        \Testo\Assert::same(\array_values($versions), ['1.5.0', '1.0.0']);
    }

    #[\Testo\Test]
    public function testWithAssetsFiltersReleasesWithAssets(): void
    {
        // Arrange - Set up assets for the second release (1.5.0)
        $release = $this->releases[1]; // 1.5.0 release

        $assets = [
            new AssetStub(
                $release,
                'package-1.5.0-linux-x64.tar.gz',
                'https://example.com/downloads/package-1.5.0-linux-x64.tar.gz',
                OperatingSystem::Linux,
                Architecture::X86_64,
            ),
        ];

        $release->setAssets($assets);

        // Act
        $result = $this->collection->withAssets();

        // Assert
        \Testo\Assert::count($result, 1);
        \Testo\Assert::same($result->first()->getName(), '1.5.0');
        \Testo\Assert::count($result->first()->getAssets(), 1);
    }

    #[\Testo\Lifecycle\BeforeTest]
    protected function setUp(): void
    {
        // Arrange
        $this->repository = new RepositoryStub('vendor/package');

        // Create a series of releases with different versions and stabilities
        $this->releases = [
            new ReleaseStub(
                $this->repository,
                '2.0.0',
                Version::fromVersionString('v2.0.0'),
                [],
            ),
            new ReleaseStub(
                $this->repository,
                '1.5.0',
                Version::fromVersionString('v1.5.0'),
                [],
            ),
            new ReleaseStub(
                $this->repository,
                '1.0.0',
                Version::fromVersionString('v1.0.0'),
                [],
            ),
            new ReleaseStub(
                $this->repository,
                '2.1.0-beta',
                Version::fromVersionString('v2.1.0-beta'),
                [],
            ),
            new ReleaseStub(
                $this->repository,
                '2.1.0-alpha',
                Version::fromVersionString('v2.1.0-alpha'),
                [],
            ),
            new ReleaseStub(
                $this->repository,
                '2.0.1-rc1',
                Version::fromVersionString('v2.0.1-rc1'),
                [],
            ),
        ];

        // Create the collection with all releases
        $this->collection = new ReleasesCollection($this->releases);
    }

    /**
     * Helper method to extract version names from a collection
     */
    private function getVersionsFromCollection(ReleasesCollection $collection): array
    {
        return \array_map(
            static fn(ReleaseInterface $release): string => $release->getName(),
            \iterator_to_array($collection),
        );
    }
}
