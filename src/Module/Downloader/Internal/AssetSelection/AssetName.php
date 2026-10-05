<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\Downloader\Internal\AssetSelection;

use Internal\DLoad\Module\Common\Libc;

/**
 * Traits of an asset read from its file name, beyond the OS and architecture the asset reports.
 *
 * @internal
 * @psalm-internal Internal\DLoad\Module\Downloader
 */
final class AssetName
{
    private function __construct(
        public readonly ?Libc $libc,
    ) {}

    public static function fromString(string $name): self
    {
        return new self(
            libc: Libc::tryFromBuildName($name),
        );
    }
}
