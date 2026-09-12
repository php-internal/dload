<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\Config\Schema;

use Internal\DLoad\Module\Common\Internal\Attribute\Env;
use Internal\DLoad\Module\Common\Internal\Attribute\InflectableConfig;
use Internal\DLoad\Module\Common\Internal\Attribute\XPath;

/**
 * @internal
 */
#[InflectableConfig]
final class Cache
{
    /** @var non-empty-string|null $dir */
    #[XPath('/dload/@cache-dir')]
    #[Env('DLOAD_CACHE_DIR')]
    public ?string $dir = null;

    #[XPath('/dload/@cache-ttl')]
    #[Env('DLOAD_CACHE_TTL')]
    public int $ttl = 600;
}
