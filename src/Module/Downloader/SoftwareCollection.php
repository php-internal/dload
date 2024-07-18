<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\Downloader;

use Internal\DLoad\Module\Common\Config\Embed\Software;
use Internal\DLoad\Module\Common\Config\SoftwareRegistry;
use IteratorAggregate;

/**
 * @implements IteratorAggregate<Software>
 */
final class SoftwareCollection implements IteratorAggregate
{
    public function __construct(
        private SoftwareRegistry $softwareRegistry,
    ) {}

    public function findSoftware(string $name): ?Software
    {
        foreach ($this->softwareRegistry->software as $software) {
            if ($software->getId() === $name) {
                return $software;
            }
        }

        return null;
    }

    /**
     * @return \Traversable<Software>
     */
    public function getIterator(): \Traversable
    {
        yield from $this->softwareRegistry->software;
    }
}
