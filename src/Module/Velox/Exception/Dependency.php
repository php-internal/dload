<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\Velox\Exception;

/**
 * Exception thrown when dependencies cannot be resolved or downloaded.
 */
final class Dependency extends Build
{
    public function __construct(
        string $message = '',
        int $code = 0,
        ?\Throwable $previous = null,
        public readonly ?string $dependencyName = null,
    ) {
        parent::__construct($message, $code, $previous);
    }
}
