<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\Cache;

use Psr\Http\Message\ResponseInterface;

/**
 * Cache for HTTP responses that may be reused between runs.
 *
 * Intended for API listings that are cheap to serve from disk and expensive in terms of API rate
 * limit: a cached listing costs no request at all.
 *
 * @internal
 */
interface ResponseCache
{
    /**
     * Returns the cached response for the key, or the result of `$fetch` when there is none.
     *
     * @param string $key Identifies the response; the request URI in practice.
     * @param \Closure(): ResponseInterface $fetch Performs the request when the cache cannot answer.
     */
    public function remember(string $key, \Closure $fetch): ResponseInterface;
}
