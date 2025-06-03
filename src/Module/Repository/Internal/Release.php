<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\Repository\Internal;

use Composer\Semver\Semver;
use Composer\Semver\VersionParser;
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
    /**
     * Normalized release name.
     *
     * @var non-empty-string
     */
    protected string $name;

    protected AssetsCollection $assets;

    /**
     * @param non-empty-string $name
     */
    public function __construct(
        protected Repository $repository,
        string $name,
        protected Version $version,
        iterable $assets = [],
    ) {
        $this->name = $this->simplifyReleaseName($name);
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

    /**
     * @param non-empty-string $name
     * @return non-empty-string
     */
    private function simplifyReleaseName(string $name): string
    {
        $version = (new VersionParser())->normalize($name);

        $parts = \explode('-', $version);
        $number = \substr($parts[0], 0, -2);

        return isset($parts[1])
            ? $number . '-' . $parts[1]
            : $number
        ;
    }
}
