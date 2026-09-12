<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\Cache\Internal;

use Internal\DLoad\Module\Cache\ResponseCache;
use Psr\Http\Message\ResponseInterface;

/**
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
