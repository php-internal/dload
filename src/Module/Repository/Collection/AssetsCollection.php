<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\Repository\Collection;

use Internal\DLoad\Module\Common\Architecture;
use Internal\DLoad\Module\Common\OperatingSystem;
use Internal\DLoad\Module\Repository\AssetInterface;
use Internal\DLoad\Module\Repository\Internal\Collection;

/**
 * Collection of release assets with filtering capabilities.
 *
 * Provides methods to filter assets by architecture, operating system,
 * file extension, and name patterns.
 *
 * ```php
 * // Get Linux x86_64 assets with certain file extensions
 * $assets = $release->getAssets()
 *     ->whereOperatingSystem(OperatingSystem::Linux)
 *     ->whereArchitecture(Architecture::X86_64)
 *     ->whereFileExtensions(['tar.gz', 'zip'])
 *     ->exceptDebPackages();
 *
 * // Find assets matching a pattern
 * $assets = $release->getAssets()->whereNameMatches('/^app-v\d+/');
 * ```
 *
 * @template-extends Collection<AssetInterface>
 * @internal
 * @psalm-internal Internal\DLoad\Module
 */
final class AssetsCollection extends Collection
{
    /**
     * Filters out Debian package assets.
     *
     * @return self New filtered collection
     */
    public function exceptDebPackages(): self
    {
        return $this->except(
            static fn(AssetInterface $asset): bool =>
            \str_ends_with(\strtolower($asset->getName()), '.deb'),
        );
    }

    /**
     * Filters assets to include only those matching the specified architecture.
     *
     * @param Architecture $arch Required architecture
     * @return self New filtered collection
     */
    public function whereArchitecture(Architecture $arch): self
    {
        return $this->filter(
            static fn(AssetInterface $asset): bool => $asset->getArchitecture() === $arch,
        );
    }

    /**
     * Filters assets to include only those matching the specified operating system.
     *
     * @param OperatingSystem $os Required operating system
     * @return self New filtered collection
     */
    public function whereOperatingSystem(OperatingSystem $os): self
    {
        return $this->filter(
            static fn(AssetInterface $asset): bool => $asset->getOperatingSystem() === $os,
        );
    }

    /**
     * Filters assets to include only those with specified file extensions.
     *
     * @param list<non-empty-string> $extensions List of file extensions without leading dot
     * @return self New filtered collection
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
     * Filters assets to include only those matching the specified format.
     *
     * @param non-empty-string $format Required format (e.g., "phar", "tar.gz", "zip")
     * @return self New filtered collection
     */
    public function whereFormat(string $format): self
    {
        return $this->filter(
            static function (AssetInterface $asset) use ($format): bool {
                $assetName = \strtolower($asset->getName());
                $normalizedFormat = \strtolower($format);
                return \str_ends_with($assetName, '.' . $normalizedFormat);
            },
        );
    }

    /**
     * Filters assets to include only those with names matching the given regex pattern.
     *
     * @param non-empty-string $pattern Regular expression pattern
     * @return self New filtered collection
     */
    public function whereNameMatches(string $pattern): self
    {
        return $this->filter(
            static fn(AssetInterface $asset): bool => @\preg_match(
                $pattern,
                $asset->getName(),
                flags: \PREG_NO_ERROR,
            ) === 1,
        );
    }
}
