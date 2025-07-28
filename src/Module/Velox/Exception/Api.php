<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\Velox\Exception;

/**
 * Exception thrown when API operations fail.
 */
final class Api extends Build
{
    public function __construct(
        string $message = '',
        int $code = 0,
        ?\Throwable $previous = null,
        public readonly ?string $endpoint = null,
        public readonly ?int $httpStatus = null,
    ) {
        parent::__construct($message, $code, $previous);
    }
}
