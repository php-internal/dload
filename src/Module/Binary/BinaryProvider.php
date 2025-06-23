<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\Binary;

use Internal\DLoad\Module\Common\FileSystem\Path;
use Internal\DLoad\Module\Config\Schema\Embed\Binary as BinaryConfig;

/**
 * Provider for obtaining binary instances.
 */
interface BinaryProvider
{
    /**
     * Gets a binary for the given configuration.
     *
     * @param Path|non-empty-string $destinationPath Directory path where binary should exist
     * @param BinaryConfig $config Binary configuration
     * @return Binary|null Binary instance or null if it doesn't exist
     */
    public function getBinary(Path|string $destinationPath, BinaryConfig $config): ?Binary;
}
