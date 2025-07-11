<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\Common;

use Internal\DLoad\Module\Binary\Binary;
use Internal\DLoad\Module\Common\FileSystem\Path;

/**
 * @internal
 */
final class DloadResult
{
    /**
     * @param array<Path> $files All the downloaded files.
     * @param Binary|null $binary Optional binary file if available.
     */
    public function __construct(
        public readonly array $files,
        public readonly ?Binary $binary = null,
    ) {}

    public static function empty(): self
    {
        return new self([]);
    }

    public static function fromBinary(Binary $binary): self
    {
        return new self([$binary->getPath()], $binary);
    }
}
