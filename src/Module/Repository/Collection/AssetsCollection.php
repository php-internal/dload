<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\Repository\Collection;

use Internal\DLoad\Module\Common\Architecture;
use Internal\DLoad\Module\Common\OperatingSystem;
use Internal\DLoad\Module\Repository\AssetInterface;
use Internal\DLoad\Module\Repository\Internal\Collection;

/**
 * @template-extends Collection<AssetInterface>
 * @internal
 * @psalm-internal Internal\DLoad\Module
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

    /**
     * @param list<non-empty-string> $extensions
     */
    public function whereFileExtensions(array $extensions): self
    {
        return $this->filter(
            static function (AssetInterface $asset) use ($extensions): bool {
                $assetName = \strtolower($asset->getName());
                foreach ($extensions as $extension) {
                    if (\str_ends_with($assetName, '.' . $extension)) {
                        return true;
                    }
                }

                return false;
            },
        );
    }

    /**
     * Select all the assets with names that match the given pattern.
     *
     * @param non-empty-string $pattern
     */
    public function whereNameMatches(string $pattern): self
    {
        return $this->filter(
            static fn(AssetInterface $asset): bool => \preg_match($pattern, $asset->getName()) === 1,
        );
    }
}
