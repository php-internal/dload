<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\Binary\Internal;

use Internal\DLoad\Module\Common\FileSystem\Path;
use Internal\DLoad\Module\Config\Schema\Embed\Binary as BinaryConfig;

/**
 * Internal implementation of Binary interface.
 *
 * @internal
 */
final class LocalBinary extends AbstractBinary
{
    private readonly Path $path;

    /**
     * @param non-empty-string $name Binary name
     * @param Path $path Path to binary
     * @param BinaryConfig $config Original configuration
     * @param BinaryExecutor $executor Binary execution service
     */
    public function __construct(
        string $name,
        BinaryConfig $config,
        BinaryExecutor $executor,
        Path $path,
    ) {
        $this->path = $path->absolute();
        parent::__construct($name, $config, $executor);
    }

    public function getPath(): Path
    {
        return $this->path;
    }
}
