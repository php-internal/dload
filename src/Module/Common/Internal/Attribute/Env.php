<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\Common\Internal\Attribute;

/**
 * Environment variable configuration attribute.
 *
 * Maps a property to an environment variable.
 *
 * @internal
 */
#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::IS_REPEATABLE)]
final class Env implements ConfigAttribute
{
    /**
     * @param non-empty-string $name Environment variable name
     */
    public function __construct(
        public string $name,
    ) {}
}
