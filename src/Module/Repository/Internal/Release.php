<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\Repository\Internal;

use Internal\DLoad\Module\Repository\Collection\AssetsCollection;
use Internal\DLoad\Module\Repository\ReleaseInterface;
use Internal\DLoad\Module\Repository\Repository;
use Internal\DLoad\Module\Version\Constraint;
use Internal\DLoad\Module\Version\Version;

/**
 * @internal
 * @psalm-internal Internal\DLoad\Module\Repository
 */
abstract class Release implements ReleaseInterface
{
    protected AssetsCollection $assets;

    /**
     * @param non-empty-string $name Release name.
     */
    public function __construct(
        protected Repository $repository,
        protected string $name,
        protected Version $version,
        iterable $assets = [],
    ) {
        $this->assets = AssetsCollection::create($assets);
    }

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
        return $this->assets;
    }

    public function satisfies(Constraint $constraint): bool
    {
        return $constraint->isSatisfiedBy($this->version);
    }
}
