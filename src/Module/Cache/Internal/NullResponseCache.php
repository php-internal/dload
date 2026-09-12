<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\Cache\Internal;

use Internal\DLoad\Module\Cache\ResponseCache;
use Psr\Http\Message\ResponseInterface;

/**
 * Cache that stores nothing: every call performs the request.
 *
 * Used when no cache directory is configured, so callers never have to check whether caching
 * is enabled.
 *
 * @internal
 * @psalm-internal Internal\DLoad
 */
final class NullResponseCache implements ResponseCache
{
    public function remember(string $key, \Closure $fetch): ResponseInterface
    {
        return $fetch();
    }
}
