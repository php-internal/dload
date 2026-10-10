<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\Repository\Internal;

/**
 * API token together with the environment variable it was read from.
 *
 * The variable name goes into error messages, so the user knows which token to fix.
 *
 * @internal
 */
final class ApiToken
{
    /**
     * @param non-empty-string $value
     * @param non-empty-string $variable
     */
    public function __construct(
        public readonly string $value,
        public readonly string $variable,
    ) {}
}
