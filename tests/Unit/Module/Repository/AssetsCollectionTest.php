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
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[\Testo\Codecov\Covers(AssetsCollection::class)]
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

    #[\Testo\Test]
    public function testWhereOperatingSystemFiltersAssetsByOs(): void
    {
        // Act
        $result = $this->collection->whereOperatingSystem(OperatingSystem::Linux);

        // Assert
        \Testo\Assert::count($result, 2);

        foreach ($result as $asset) {
            \Testo\Assert::same($asset->getOperatingSystem(), OperatingSystem::Linux);
        }
    }

    #[\Testo\Test]
    public function testWhereArchitectureFiltersAssetsByArchitecture(): void
    {
        // Act
        $result = $this->collection->whereArchitecture(Architecture::ARM_64);

        // Assert
        \Testo\Assert::count($result, 3);

        foreach ($result as $asset) {
            \Testo\Assert::same($asset->getArchitecture(), Architecture::ARM_64);
        }
    }

    #[\Testo\Data\DataProvider('provideNamePatterns')]
    #[\Testo\Test]
    public function testWhereNameMatchesFiltersAssetsByNamePattern(
        string $pattern,
        array $expectedMatches,
    ): void {
        // Act
        $result = $this->collection->whereNameMatches($pattern);

        // Assert
        \Testo\Assert::count($result, \count($expectedMatches));

        $actualNames = \array_map(
            static fn($asset) => $asset->getName(),
            \iterator_to_array($result),
        );

        foreach ($expectedMatches as $expectedName) {
            \Testo\Assert::contains($actualNames, $expectedName);
        }
    }

    #[\Testo\Test]
    public function testWhereNameMatchesWithInvalidPattern(): void
    {
        // Act
        $new = $this->collection->whereNameMatches('/invalid[pattern/');

        // Assert
        \Testo\Assert::count($new, 0);
    }

    #[\Testo\Test]
    public function testChainedFiltersWorkCorrectly(): void
    {
        // Act
        $result = $this->collection
            ->whereOperatingSystem(OperatingSystem::Linux)
            ->whereArchitecture(Architecture::ARM_64);

        // Assert
        \Testo\Assert::count($result, 1);
        $asset = $result->first();
        \Testo\Assert::same($asset->getName(), 'package-1.2.3-linux-arm64.tar.gz');
    }

    #[\Testo\Test]
    public function testFirstReturnsFirstAssetOrNull(): void
    {
        // Act with non-empty collection
        $first = $this->collection->first();

        // Assert
        \Testo\Assert::notNull($first);
        \Testo\Assert::same($first->getName(), 'package-1.2.3-linux-x64.tar.gz');

        // Act with empty collection
        $empty = new AssetsCollection([]);
        $result = $empty->first();

        // Assert
        \Testo\Assert::null($result);
    }

    #[\Testo\Test]
    public function testEmptyReturnsTrueForEmptyCollection(): void
    {
        // Act with non-empty collection
        $resultNonEmpty = $this->collection->empty();

        // Assert
        \Testo\Assert::false($resultNonEmpty);

        // Act with empty collection
        $empty = new AssetsCollection([]);
        $resultEmpty = $empty->empty();

        // Assert
        \Testo\Assert::true($resultEmpty);
    }

    #[\Testo\Lifecycle\BeforeTest]
    protected function setUp(): void
    {
        // Arrange
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
