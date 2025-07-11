<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\Velox;

use Internal\DLoad\Module\Binary\Binary;

/**
 * Represents the result of a successful build operation.
 *
 * Contains information about the built binary, build metadata,
 * and any additional artifacts produced during the build process.
 *
 * @internal
 */
final class Result
{
    /**
     * Creates a new build result.
     *
     * @param Binary $binary The built binary
     * @param array<non-empty-string, mixed> $metadata Additional build metadata
     */
    public function __construct(
        public readonly Binary $binary,
        public readonly array $metadata = [],
    ) {}
}
