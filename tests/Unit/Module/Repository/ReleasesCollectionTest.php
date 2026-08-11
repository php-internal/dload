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
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Lifecycle\BeforeTest;
use Testo\Test;

#[Covers(ReleasesCollection::class)]
final class ReleasesCollectionTest
{
    private RepositoryStub $repository;

    /** @var list<ReleaseStub> */
    private array $releases;

    private ReleasesCollection $collection;

    #[Test]
    public function satisfiesFiltersReleasesByVersionConstraint(): void
    {
        $result = $this->collection->satisfies(Constraint::fromConstraintString('^1.0.0'));

        Assert::count($result, 2, 'Should only include 1.0.0 and 1.5.0 versions');

        $versions = \array_map(
            static fn(ReleaseInterface $release): string => $release->getName(),
            \iterator_to_array($result),
        );

        Assert::contains($versions, '1.0.0');
        Assert::contains($versions, '1.5.0');
        Assert::iterable($versions)->notContains('2.0.0');
    }

    #[Test]
    public function notSatisfiesFiltersOutReleasesByVersionConstraint(): void
    {
        $result = $this->collection->notSatisfies(Constraint::fromConstraintString('^1.0.0'));

        Assert::int($result->count())->greaterThan(0);
        Assert::iterable($this->getVersionsFromCollection($result))->notContains('1.0.0');
        Assert::iterable($this->getVersionsFromCollection($result))->notContains('1.5.0');
        Assert::contains($this->getVersionsFromCollection($result), '2.0.0');
    }

    #[Test]
    public function stabilityFiltersReleasesByExactStability(): void
    {
        $result = $this->collection->stability(Stability::Beta);

        Assert::count($result, 1);
        Assert::same($result->first()->getName(), '2.1.0-beta');
    }

    #[Test]
    public function stableFiltersToOnlyStableReleases(): void
    {
        $result = $this->collection->stable();

        Assert::count($result, 3, 'Should only include stable versions');

        foreach ($result as $release) {
            Assert::same($release->getVersion()->stability, Stability::Stable);
        }
    }

    #[Test]
    public function minimumStabilityFiltersReleasesByMinimumStabilityLevel(): void
    {
        $result = $this->collection->minimumStability(Stability::RC);

        $stabilities = [];
        foreach ($result as $release) {
            $stabilities[] = $release->getVersion()->stability;
        }

        Assert::contains($stabilities, Stability::Stable);
        Assert::contains($stabilities, Stability::RC);
        Assert::iterable($stabilities)->notContains(Stability::Beta);
        Assert::iterable($stabilities)->notContains(Stability::Alpha);
    }

    #[Test]
    public function sortByVersionSortsReleasesByVersionDescending(): void
    {
        $result = $this->collection->sortByVersion();
        $versions = $this->getVersionsFromCollection($result);

        Assert::same(\array_values($versions), [
            // Expected order by semantic version, newest first
            '2.1.0-beta',
            '2.1.0-alpha',
            '2.0.1-rc1',
            '2.0.0',
            '1.5.0',
            '1.0.0',
        ]);
    }

    #[Test]
    public function chainedFiltersWorkCorrectly(): void
    {
        // Act - Get stable releases that satisfy version constraint and sort them
        $result = $this->collection
            ->stable()
            ->satisfies(Constraint::fromConstraintString('^1.0.0'))
            ->sortByVersion();

        $versions = $this->getVersionsFromCollection($result);
        Assert::same(\array_values($versions), ['1.5.0', '1.0.0']);
    }

    #[Test]
    public function withAssetsFiltersReleasesWithAssets(): void
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

        $result = $this->collection->withAssets();

        Assert::count($result, 1);
        Assert::same($result->first()->getName(), '1.5.0');
        Assert::count($result->first()->getAssets(), 1);
    }

    #[BeforeTest]
    protected function prepare(): void
    {
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
