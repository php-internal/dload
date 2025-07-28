<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\Velox\Exception;

/**
 * Exception thrown when configuration is invalid or incomplete.
 */
final class Config extends Build
{
    public function __construct(
        string $message = '',
        int $code = 0,
        ?\Throwable $previous = null,
        public readonly ?string $configPath = null,
    ) {
        parent::__construct($message, $code, $previous);
    }
}
