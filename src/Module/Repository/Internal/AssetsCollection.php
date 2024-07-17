<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\Repository\Internal;

use Internal\DLoad\Module\Environment\Architecture;
use Internal\DLoad\Module\Environment\OperatingSystem;
use Internal\DLoad\Module\Repository\AssetInterface;

/**
 * @template-extends Collection<AssetInterface>
 * @internal
 * @psalm-internal Internal\DLoad\Module\Repository
 */
final class AssetsCollection extends Collection
{
    public function exceptDebPackages(): self
    {
        return $this->except(
            static fn(AssetInterface $asset): bool =>
            \str_ends_with(\strtolower($asset->getName()), '.deb'),
        );
    }

    public function whereArchitecture(Architecture $arch): self
    {
        return $this->filter(
            static fn(AssetInterface $asset): bool => $asset->getArchitecture() === $arch,
        );
    }

    public function whereOperatingSystem(OperatingSystem $os): self
    {
        return $this->filter(
            static fn(AssetInterface $asset): bool => $asset->getOperatingSystem() === $os,
        );
    }
}
