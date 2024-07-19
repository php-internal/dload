<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\Downloader;

use Internal\DLoad\Info;
use Internal\DLoad\Module\Common\Config\CustomSoftwareRegistry;
use Internal\DLoad\Module\Common\Config\Embed\Software;
use IteratorAggregate;

/**
 * @implements IteratorAggregate<Software>
 */
final class SoftwareCollection implements \IteratorAggregate, \Countable
{
    /** @var array<non-empty-string, Software> */
    private array $registry = [];

    public function __construct(
        CustomSoftwareRegistry $softwareRegistry,
    ) {
        foreach ($softwareRegistry->software as $software) {
            $this->registry[$software->getId()] = $software;
        }

        $softwareRegistry->overwrite or $this->loadDefaultRegistry();
    }

    public function findSoftware(string $name): ?Software
    {
        return $this->registry[$name] ?? null;
    }

    /**
     * @return \Traversable<Software>
     */
    public function getIterator(): \Traversable
    {
        yield from $this->registry;
    }

    /**
     * @return int<0, max>
     */
    public function count(): int
    {
        return \count($this->registry);
    }

    private function loadDefaultRegistry(): void
    {
        $json = \json_decode(
            \file_get_contents(Info::ROOT_DIR . '/resources/software.json'),
            true,
            16,
            JSON_THROW_ON_ERROR,
        );

        foreach ($json as $softwareArray) {
            $software = Software::fromArray($softwareArray);
            $this->registry[$software->getId()] ??= $software;
        }
    }
}
