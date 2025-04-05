<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\Repository;

use Internal\DLoad\Module\Common\Architecture;
use Internal\DLoad\Module\Common\OperatingSystem;

/**
 * Represents a downloadable asset from a software release.
 *
 * Assets are individual binary files or archives associated with a specific release,
 * often targeting particular operating systems or architectures.
 */
interface AssetInterface
{
    /**
     * Returns the release this asset belongs to.
     *
     * @return ReleaseInterface The parent release
     */
    public function getRelease(): ReleaseInterface;

    /**
     * Returns the name of the asset.
     *
     * @return non-empty-string Asset name, typically the filename
     */
    public function getName(): string;

    /**
     * Returns the URI from which the asset can be downloaded.
     *
     * @return non-empty-string Download URI
     */
    public function getUri(): string;

    /**
     * Returns the operating system this asset is compatible with, if specified.
     *
     * @return OperatingSystem|null The target OS or null if not OS-specific
     */
    public function getOperatingSystem(): ?OperatingSystem;

    /**
     * Returns the CPU architecture this asset is compatible with, if specified.
     *
     * @return Architecture|null The target architecture or null if not architecture-specific
     */
    public function getArchitecture(): ?Architecture;

    /**
     * Downloads the asset content.
     *
     * @return \Traversable<non-empty-string> Stream of content chunks
     */
    public function download(): \Traversable;
}
