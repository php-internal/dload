<?php

declare(strict_types=1);

namespace Internal\DLoad\Tests\Unit\Module\Repository\Stub;

use Composer\Semver\Semver;
use Internal\DLoad\Module\Common\Stability;
use Internal\DLoad\Module\Repository\AssetInterface;
use Internal\DLoad\Module\Repository\Collection\AssetsCollection;
use Internal\DLoad\Module\Repository\ReleaseInterface;
use Internal\DLoad\Module\Repository\Repository;

/**
 * Test stub implementation of ReleaseInterface for unit tests.
 */
final class ReleaseStub implements ReleaseInterface
{
    /**
     * @param non-empty-string $name Formatted version (e.g. "1.2.3")
     * @param non-empty-string $version Raw version (e.g. "v1.2.3-beta")
     * @param array<AssetInterface> $assets
     */
    public function __construct(
        private readonly RepositoryStub $repository,
        private readonly string $name,
        private readonly string $version,
        private readonly Stability $stability,
        private array $assets = [],
    ) {}

    public function getRepository(): Repository
    {
        return $this->repository;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getVersion(): string
    {
        return $this->version;
    }

    public function getStability(): Stability
    {
        return $this->stability;
    }

    public function getAssets(): AssetsCollection
    {
        return new AssetsCollection($this->assets);
    }

    /**
     * @param array<AssetInterface> $assets
     */
    public function setAssets(array $assets): void
    {
        $this->assets = $assets;
    }

    public function satisfies(string $constraint): bool
    {
        // Using Composer's semver for consistent version comparison
        return Semver::satisfies($this->name, $constraint);
    }
}
