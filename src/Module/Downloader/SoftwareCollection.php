<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\Downloader;

use Internal\DLoad\Info;
use Internal\DLoad\Module\Config\Schema\CustomSoftwareRegistry;
use Internal\DLoad\Module\Config\Schema\Embed\Software;
use IteratorAggregate;

/**
 * Collection of software package configurations.
 *
 * Manages both custom and default software registry entries.
 * Provides lookup functionality to find software by name or alias.
 *
 * @implements IteratorAggregate<Software>
 */
final class SoftwareCollection implements \IteratorAggregate, \Countable
{
    /** @var array<non-empty-string, Software> Map of software ID to configuration */
    private array $registry = [];

    /**
     * Creates a collection from registry and optionally loads default entries.
     *
     * Processes custom registry entries first, then loads default registry unless
     * overwrite flag is set in the custom registry.
     *
     * @param CustomSoftwareRegistry $softwareRegistry Custom software configuration registry
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
     * Searches for software using exact name or alias match.
     *
     * ```php
     * $software = $collection->findSoftware('rr') ?? throw new \RuntimeException('Software not found');
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
     * Returns iterator for all software configurations.
     *
     * @return \Traversable<Software>
     */
    public function getIterator(): \Traversable
    {
        yield from $this->registry;
    }

    /**
     * Returns the number of software packages in the collection.
     *
     * @return int<0, max> Number of software packages
     */
    public function count(): int
    {
        return \count($this->registry);
    }

    /**
     * Loads default software registry from the embedded JSON file.
     *
     * Parses the default software.json file and adds entries to the registry
     * without overwriting existing entries.
     *
     * @link resources/software.json
     * @see Software::fromArray() For parsing logic
     */
    private function loadDefaultRegistry(): void
    {
        $json = (array) \json_decode(
            (string) \file_get_contents(Info::ROOT_DIR . '/resources/software.json'),
            true,
            16,
            JSON_THROW_ON_ERROR,
        );

        foreach ($json['software'] ?? [] as $softwareArray) {
            $software = Software::fromArray($softwareArray);
            $this->registry[$software->getId()] ??= $software;
        }
    }
}
