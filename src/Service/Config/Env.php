<?php

declare(strict_types=1);

namespace Internal\DLoad\Service\Config;

/**
 * @internal
 */
#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::IS_REPEATABLE)]
final class Env implements ConfigAttribute
{
    public function __construct(
        public string $name,
    ) {}
}
