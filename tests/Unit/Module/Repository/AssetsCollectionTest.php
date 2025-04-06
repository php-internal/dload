<?php

declare(strict_types=1);

namespace Internal\DLoad\Tests\Unit\Module\Repository;

use Internal\DLoad\Module\Common\Architecture;
use Internal\DLoad\Module\Common\OperatingSystem;
use Internal\DLoad\Module\Common\Stability;
use Internal\DLoad\Module\Repository\Collection\AssetsCollection;
use Internal\DLoad\Tests\Unit\Module\Repository\Stub\AssetStub;
use Internal\DLoad\Tests\Unit\Module\Repository\Stub\ReleaseStub;
use Internal\DLoad\Tests\Unit\Module\Repository\Stub\RepositoryStub;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(AssetsCollection::class)]
final class AssetsCollectionTest extends TestCase
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

    public function testWhereOperatingSystemFiltersAssetsByOs(): void
    {
        // Act
        $result = $this->collection->whereOperatingSystem(OperatingSystem::Linux);

        // Assert
        self::assertCount(2, $result);

        foreach ($result as $asset) {
            self::assertSame(OperatingSystem::Linux, $asset->getOperatingSystem());
        }
    }

    public function testWhereArchitectureFiltersAssetsByArchitecture(): void
    {
        // Act
        $result = $this->collection->whereArchitecture(Architecture::ARM_64);

        // Assert
        self::assertCount(3, $result);

        foreach ($result as $asset) {
            self::assertSame(Architecture::ARM_64, $asset->getArchitecture());
        }
    }

    #[DataProvider('provideNamePatterns')]
    public function testWhereNameMatchesFiltersAssetsByNamePattern(
        string $pattern,
        array $expectedMatches,
    ): void {
        // Act
        $result = $this->collection->whereNameMatches($pattern);

        // Assert
        self::assertCount(\count($expectedMatches), $result);

        $actualNames = \array_map(
            static fn($asset) => $asset->getName(),
            \iterator_to_array($result),
        );

        foreach ($expectedMatches as $expectedName) {
            self::assertContains($expectedName, $actualNames);
        }
    }

    public function testWhereNameMatchesWithInvalidPattern(): void
    {
        // Act
        $new = $this->collection->whereNameMatches('/invalid[pattern/');

        // Assert
        self::assertCount(0, $new);
    }

    public function testChainedFiltersWorkCorrectly(): void
    {
        // Act
        $result = $this->collection
            ->whereOperatingSystem(OperatingSystem::Linux)
            ->whereArchitecture(Architecture::ARM_64);

        // Assert
        self::assertCount(1, $result);
        $asset = $result->first();
        self::assertSame('package-1.2.3-linux-arm64.tar.gz', $asset->getName());
    }

    public function testFirstReturnsFirstAssetOrNull(): void
    {
        // Act with non-empty collection
        $first = $this->collection->first();

        // Assert
        self::assertNotNull($first);
        self::assertSame('package-1.2.3-linux-x64.tar.gz', $first->getName());

        // Act with empty collection
        $empty = new AssetsCollection([]);
        $result = $empty->first();

        // Assert
        self::assertNull($result);
    }

    public function testEmptyReturnsTrueForEmptyCollection(): void
    {
        // Act with non-empty collection
        $resultNonEmpty = $this->collection->empty();

        // Assert
        self::assertFalse($resultNonEmpty);

        // Act with empty collection
        $empty = new AssetsCollection([]);
        $resultEmpty = $empty->empty();

        // Assert
        self::assertTrue($resultEmpty);
    }

    protected function setUp(): void
    {
        // Arrange
        $this->repository = new RepositoryStub('vendor/package', []);
        $this->release = new ReleaseStub(
            $this->repository,
            '1.2.3',
            'v1.2.3',
            Stability::Stable,
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
