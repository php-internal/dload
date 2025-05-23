<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\Repository\Internal;

use Composer\Semver\Semver;
use Composer\Semver\VersionParser;
use Internal\DLoad\Module\Common\Stability;
use Internal\DLoad\Module\Repository\Collection\AssetsCollection;
use Internal\DLoad\Module\Repository\ReleaseInterface;
use Internal\DLoad\Module\Repository\Repository;

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

    protected Stability $stability;
    protected AssetsCollection $assets;

    /**
     * @param non-empty-string $name
     * @param non-empty-string $version
     */
    public function __construct(
        protected Repository $repository,
        string $name,
        protected string $version,
        ?Stability $stability = null,
        iterable $assets = [],
    ) {
        $this->name = $this->simplifyReleaseName($name);
        $this->assets = AssetsCollection::create($assets);
        $this->stability = $stability ?? $this->parseStability($version);
    }

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
        return $this->assets;
    }

    public function satisfies(string $constraint): bool
    {
        return Semver::satisfies($this->getName(), $constraint);
    }

    /**
     * @param non-empty-string $version
     */
    private function parseStability(string $version): Stability
    {
        return Stability::parse($version);
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
