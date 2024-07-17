<?php

declare(strict_types=1);

namespace Internal\DLoad\Service\Config;

/**
 * @internal
 */
#[\Attribute(\Attribute::TARGET_PROPERTY)]
final class XPath implements ConfigAttribute
{
    public function __construct(
        public string $path,
        public int $key = 0,
    ) {}
}
