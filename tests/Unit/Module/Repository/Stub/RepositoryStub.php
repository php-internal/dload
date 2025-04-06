<?php

declare(strict_types=1);

namespace Internal\DLoad\Tests\Unit\Module\Repository\Stub;

use Internal\DLoad\Module\Repository\Collection\ReleasesCollection;
use Internal\DLoad\Module\Repository\Internal\Collection;
use Internal\DLoad\Module\Repository\ReleaseInterface;
use Internal\DLoad\Module\Repository\Repository;
use Internal\DLoad\Tests\Unit\Module\Repository\Stub\Collection\ReleasesCollectionStub;

/**
 * Stub implementation of Repository for testing.
 */
final class RepositoryStub implements Repository
{
    private string $name;

    /** @var Collection<ReleaseInterface> */
    private ?Collection $releases;

    /**
     * @param string $name Repository name
     * @param ReleasesCollection|null $releases Collection of releases to return
     */
    public function __construct(string $name, ?ReleasesCollection $releases = null)
    {
        $this->name = $name;
        $this->releases = $releases ?? new ReleasesCollectionStub([]);
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getReleases(): ReleasesCollection
    {
        return $this->releases;
    }

    public function setAsset(ReleaseStub $release, array $assets): void
    {
        $release->setAssets($assets);
    }
}
