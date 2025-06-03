<?php

declare(strict_types=1);

namespace Internal\DLoad\Tests\Unit\Module\Repository\Stub;

use Internal\DLoad\Module\Repository\AssetInterface;
use Internal\DLoad\Module\Repository\Collection\AssetsCollection;
use Internal\DLoad\Module\Repository\ReleaseInterface;
use Internal\DLoad\Module\Repository\Repository;
use Internal\DLoad\Module\Version\Constraint;
use Internal\DLoad\Module\Version\Version;

/**
 * Test stub implementation of ReleaseInterface for unit tests.
 */
final class ReleaseStub implements ReleaseInterface
{
    /**
     * @param non-empty-string $name Formatted version (e.g. "1.2.3")
     * @param array<AssetInterface> $assets
     */
    public function __construct(
        private readonly RepositoryStub $repository,
        private readonly string $name,
        private readonly Version $version,
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

    public function getVersion(): Version
    {
        return $this->version;
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

    public function satisfies(Constraint $constraint): bool
    {
        return $constraint->isSatisfiedBy($this->version);
    }
}
