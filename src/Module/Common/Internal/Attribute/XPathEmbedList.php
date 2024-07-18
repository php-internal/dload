<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\Common\Internal\Attribute;

/**
 * @internal
 */
#[\Attribute(\Attribute::TARGET_PROPERTY)]
final class XPathEmbedList implements ConfigAttribute
{
    /**
     * @param non-empty-string $path
     * @param class-string $class
     */
    public function __construct(
        public string $path,
        public string $class,
    ) {}
}
