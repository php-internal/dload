<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\Software;

use Internal\DLoad\Module\Common\Pipeline\Pipeline;
use Internal\DLoad\Module\Config\Schema\CustomSoftwareRegistry;
use Internal\DLoad\Module\Software\Internal\SoftwareSource;
use Internal\DLoad\Module\Software\Internal\Source\ConfigSource;
use Internal\DLoad\Module\Software\Internal\Source\OfficialSource;

/**
 * Builds the {@see SoftwareCollection} by passing it through every software source.
 *
 * ```php
 * $collection = $container->get(SoftwareCollector::class)->collect();
 * ```
 */
final class SoftwareCollector
{
    /** @var callable(SoftwareCollection): SoftwareCollection */
    private $pipeline;

    public function __construct(CustomSoftwareRegistry $registry)
    {
        /**
         * The config source goes first: it is the one that can cut the sources after it.
         *
         * @see SoftwareSource::collect()
         * @var callable(SoftwareCollection): SoftwareCollection $pipeline
         */
        $pipeline = Pipeline::prepare(
            new ConfigSource($registry),
            new OfficialSource(),
        )->with(static fn(SoftwareCollection $collection): SoftwareCollection => $collection, 'collect');
        $this->pipeline = $pipeline;
    }

    public function collect(): SoftwareCollection
    {
        return ($this->pipeline)(SoftwareCollection::empty());
    }
}
