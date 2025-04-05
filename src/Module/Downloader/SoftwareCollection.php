<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\Downloader;

use Internal\DLoad\Info;
use Internal\DLoad\Module\Common\Config\CustomSoftwareRegistry;
use Internal\DLoad\Module\Common\Config\Embed\Software;
use IteratorAggregate;

/**
 * Collection of software package configurations.
 *
 * @implements IteratorAggregate<Software>
 */
final class SoftwareCollection implements \IteratorAggregate, \Countable
{
    /** @var array<non-empty-string, Software> */
    private array $registry = [];

    /**
     * Creates a collection from registry and optionally loads default entries.
     */
    public function __construct(CustomSoftwareRegistry $softwareRegistry)
    {
        foreach ($softwareRegistry->software as $software) {
            $this->registry[$software->getId()] = $software;
        }

        $softwareRegistry->overwrite or $this->loadDefaultRegistry();
    }

    /**
     * Finds software configuration by name.
     *
     * ```php
     * $software = $collection->findSoftware('rr');
     * if ($software !== null) {
     *     // Process software configuration
     * }
     * ```
     *
     * @param non-empty-string $name Software name or alias
     * @return Software|null Found software configuration or null
     */
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

    public function count(): int
    {
        return \count($this->registry);
    }

    /**
     * Loads default software registry from embedded JSON file.
     *
     * @see Software::fromArray() For parsing logic
     */
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
