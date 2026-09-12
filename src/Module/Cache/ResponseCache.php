<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\Cache;

use Psr\Http\Message\ResponseInterface;

/**
 * @internal
 */
interface ResponseCache
{
    /**
     * @param \Closure(): ResponseInterface $fetch
     */
    public function remember(string $key, \Closure $fetch): ResponseInterface;
}
