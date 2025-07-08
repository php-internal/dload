<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\Velox\Exception;

/**
 * Base exception for all Velox build operations.
 */
class Build extends \RuntimeException
{
    public function __construct(
        string $message = '',
        int $code = 0,
        ?\Throwable $previous = null,
        public readonly ?string $buildOutput = null,
    ) {
        parent::__construct($message, $code, $previous);
    }
}
