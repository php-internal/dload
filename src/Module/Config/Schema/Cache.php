<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\Config\Schema;

use Internal\DLoad\Module\Common\Internal\Attribute\Env;
use Internal\DLoad\Module\Common\Internal\Attribute\InflectableConfig;
use Internal\DLoad\Module\Common\Internal\Attribute\InputOption;
use Internal\DLoad\Module\Common\Internal\Attribute\XPath;

/**
 * Version registry configuration.
 *
 * The registry is a local database of the releases every known repository offers. Versions in it
 * never expire; what expires is the last check against the source, so repeated runs within the
 * TTL are served from disk without touching the API rate limit.
 *
 * @internal
 */
#[InflectableConfig]
final class Cache
{
    /**
     * @var non-empty-string|null $dir Directory of the version registry.
     *      When not set, the per-user cache directory of the platform is used.
     */
    #[XPath('/dload/@cache-dir')]
    #[Env('DLOAD_CACHE_DIR')]
    public ?string $dir = null;

    /**
     * @var int $ttl Number of seconds the last check of a repository stays valid.
     *      `0` disables the registry entirely.
     */
    #[XPath('/dload/@cache-ttl')]
    #[Env('DLOAD_CACHE_TTL')]
    public int $ttl = 600;

    /** @var bool $refresh Ignore the TTL and check every repository against its source once. */
    #[InputOption('refresh')]
    public bool $refresh = false;
}
