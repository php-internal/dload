<?php

declare(strict_types=1);

namespace Internal\DLoad\Tests\Unit\Module\Repository;

use Internal\DLoad\Module\Common\Architecture;
use Internal\DLoad\Module\Common\OperatingSystem;
use Internal\DLoad\Module\Repository\Collection\AssetsCollection;
use Internal\DLoad\Module\Version\Version;
use Internal\DLoad\Tests\Unit\Module\Repository\Stub\AssetStub;
use Internal\DLoad\Tests\Unit\Module\Repository\Stub\ReleaseStub;
use Internal\DLoad\Tests\Unit\Module\Repository\Stub\RepositoryStub;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Data\DataProvider;
use Testo\Lifecycle\BeforeTest;
use Testo\Test;

#[Covers(AssetsCollection::class)]
final class AssetsCollectionTest
{
    private RepositoryStub $repository;
    private ReleaseStub $release;
    private array $assets;
    private AssetsCollection $collection;

    public static function provideNamePatterns(): \Generator
    {
        yield 'exact linux x64 asset' => [
            '/^package-1\.2\.3-linux-x64\.tar\.gz$/',
            ['package-1.2.3-linux-x64.tar.gz'],
        ];

        yield 'all linux assets' => [
            '/linux/',
            [
                'package-1.2.3-linux-x64.tar.gz',
                'package-1.2.3-linux-arm64.tar.gz',
            ],
        ];

        yield 'all tar.gz assets' => [
            '/\.tar\.gz$/',
            [
                'package-1.2.3-linux-x64.tar.gz',
                'package-1.2.3-linux-arm64.tar.gz',
                'package-1.2.3-darwin-x64.tar.gz',
            ],
        ];

        yield 'all zip assets' => [
            '/\.zip$/',
            [
                'package-1.2.3-windows-x64.zip',
                'package-1.2.3-source.zip',
            ],
        ];

        yield 'no matches' => [
            '/nonexistent/',
            [],
        ];
    }

    #[Test]
    public function whereOperatingSystemFiltersAssetsByOs(): void
    {
        $result = $this->collection->whereOperatingSystem(OperatingSystem::Linux);

        Assert::count($result, 2);

        foreach ($result as $asset) {
            Assert::same($asset->getOperatingSystem(), OperatingSystem::Linux);
        }
    }

    #[Test]
    public function whereArchitectureFiltersAssetsByArchitecture(): void
    {
        $result = $this->collection->whereArchitecture(Architecture::ARM_64);

        Assert::count($result, 3);

        foreach ($result as $asset) {
            Assert::same($asset->getArchitecture(), Architecture::ARM_64);
        }
    }

    #[DataProvider('provideNamePatterns')]
    #[Test]
    public function whereNameMatchesFiltersAssetsByNamePattern(
        string $pattern,
        array $expectedMatches,
    ): void {
        $result = $this->collection->whereNameMatches($pattern);

        Assert::count($result, \count($expectedMatches));

        $actualNames = \array_map(
            static fn($asset) => $asset->getName(),
            \iterator_to_array($result),
        );

        foreach ($expectedMatches as $expectedName) {
            Assert::contains($actualNames, $expectedName);
        }
    }

    #[Test]
    public function whereNameMatchesWithInvalidPattern(): void
    {
        $new = $this->collection->whereNameMatches('/invalid[pattern/');

        Assert::count($new, 0);
    }

    #[Test]
    public function chainedFiltersWorkCorrectly(): void
    {
        $result = $this->collection
            ->whereOperatingSystem(OperatingSystem::Linux)
            ->whereArchitecture(Architecture::ARM_64);

        Assert::count($result, 1);
        $asset = $result->first();
        Assert::same($asset->getName(), 'package-1.2.3-linux-arm64.tar.gz');
    }

    #[Test]
    public function firstReturnsFirstAssetOrNull(): void
    {
        // Act with non-empty collection
        $first = $this->collection->first();

        Assert::notNull($first);
        Assert::same($first->getName(), 'package-1.2.3-linux-x64.tar.gz');

        // Act with empty collection
        $empty = new AssetsCollection([]);
        $result = $empty->first();

        Assert::null($result);
    }

    #[Test]
    public function emptyReturnsTrueForEmptyCollection(): void
    {
        // Act with non-empty collection
        $resultNonEmpty = $this->collection->empty();

        Assert::false($resultNonEmpty);

        // Act with empty collection
        $empty = new AssetsCollection([]);
        $resultEmpty = $empty->empty();

        Assert::true($resultEmpty);
    }

    #[BeforeTest]
    protected function prepare(): void
    {
        $this->repository = new RepositoryStub('vendor/package');
        $this->release = new ReleaseStub(
            $this->repository,
            '1.2.3',
            Version::fromVersionString('v1.2.3'),
            [],
        );

        // Create a variety of assets with different characteristics
        $this->assets = [
            new AssetStub(
                $this->release,
                'package-1.2.3-linux-x64.tar.gz',
                'https://example.com/downloads/package-1.2.3-linux-x64.tar.gz',
                OperatingSystem::Linux,
                Architecture::X86_64,
            ),
            new AssetStub(
                $this->release,
                'package-1.2.3-linux-arm64.tar.gz',
                'https://example.com/downloads/package-1.2.3-linux-arm64.tar.gz',
                OperatingSystem::Linux,
                Architecture::ARM_64,
            ),
            new AssetStub(
                $this->release,
                'package-1.2.3-windows-x64.zip',
                'https://example.com/downloads/package-1.2.3-windows-x64.zip',
                OperatingSystem::Windows,
                Architecture::ARM_64,
            ),
            new AssetStub(
                $this->release,
                'package-1.2.3-darwin-x64.tar.gz',
                'https://example.com/downloads/package-1.2.3-darwin-x64.tar.gz',
                OperatingSystem::Darwin,
                Architecture::ARM_64,
            ),
            new AssetStub(
                $this->release,
                'package-1.2.3-source.zip',
                'https://example.com/downloads/package-1.2.3-source.zip',
                null,
                null,
            ),
        ];

        $this->collection = new AssetsCollection($this->assets);
    }
}
