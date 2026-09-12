<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\Config\Schema;

use Internal\DLoad\Module\Common\Internal\Attribute\Env;
use Internal\DLoad\Module\Common\Internal\Attribute\InflectableConfig;
use Internal\DLoad\Module\Common\Internal\Attribute\XPath;

/**
 * Release listings cache configuration.
 *
 * Caching is off until a directory is configured. A directory that survives between runs (a CI
 * cache, for example) lets repeated runs read release listings from disk instead of spending the
 * API rate limit on them.
 *
 * @internal
 */
#[InflectableConfig]
final class Cache
{
    /** @var non-empty-string|null $dir Directory to store cached release listings in */
    #[XPath('/dload/@cache-dir')]
    #[Env('DLOAD_CACHE_DIR')]
    public ?string $dir = null;

    /** @var int $ttl Number of seconds a cached release listing stays usable */
    #[XPath('/dload/@cache-ttl')]
    #[Env('DLOAD_CACHE_TTL')]
    public int $ttl = 600;
}
