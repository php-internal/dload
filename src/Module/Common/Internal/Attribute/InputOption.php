<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\Common\Internal\Attribute;

/**
 * Command line option configuration attribute.
 *
 * Maps a property to a command line option.
 *
 * @internal
 */
#[\Attribute(\Attribute::TARGET_PROPERTY)]
final class InputOption implements ConfigAttribute
{
    /**
     * @param non-empty-string $name Option name
     */
    public function __construct(
        public string $name,
    ) {}
}
