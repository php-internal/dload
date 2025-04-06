<?php

declare(strict_types=1);

namespace Internal\DLoad\Tests\Unit\Module\Repository\Stub;

use Internal\DLoad\Module\Repository\AssetInterface;
use Internal\DLoad\Module\Repository\Collection\ReleasesCollection;
use Internal\DLoad\Module\Repository\ReleaseInterface;
use Internal\DLoad\Module\Repository\RepositoryInterface;

/**
 * Test stub implementation of RepositoryInterface for unit tests.
 */
final class RepositoryStub implements RepositoryInterface
{
    /**
     * @var array<ReleaseInterface>
     */
    private array $releases;

    /**
     * @var array<string, array<AssetInterface>> Mapping of release name to assets
     */
    private array $assetsMap = [];

    /**
     * @param non-empty-string $name
     * @param array<ReleaseInterface> $releases
     */
    public function __construct(
        private string $name,
        array $releases,
    ) {
        $this->releases = $releases;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getReleases(): ReleasesCollection
    {
        return new ReleasesCollection($this->releases);
    }

    /**
     * Set assets for a specific release in this repository.
     * Helper method for testing.
     *
     * @param array<AssetInterface> $assets
     */
    public function setAssets(array $assets, ReleaseInterface $release): void
    {
        $this->assetsMap[$release->getName()] = $assets;
    }

    /**
     * Get assets for a specific release.
     * Helper method for testing.
     *
     * @return array<AssetInterface>
     */
    public function getAssetsForRelease(ReleaseInterface $release): array
    {
        return $this->assetsMap[$release->getName()] ?? [];
    }
}
