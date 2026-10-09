<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\Software;

use Internal\DLoad\Module\Config\Schema\Embed\Software;

/**
 * Software definition together with the source that declared it.
 */
final readonly class Entry
{
    public function __construct(
        public Software $software,
        public Origin $origin,
    ) {}

    /**
     * @return non-empty-string
     */
    public function getId(): string
    {
        return $this->software->getId();
    }
}
