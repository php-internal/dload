<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\Software\Internal\Source;

use Internal\DLoad\Module\Config\Schema\CustomSoftwareRegistry;
use Internal\DLoad\Module\Software\Internal\SoftwareSource;
use Internal\DLoad\Module\Software\Origin;
use Internal\DLoad\Module\Software\SoftwareCollection;

/**
 * Adds the software defined in the `<registry>` section of the project config.
 *
 * With `<registry overwrite="true">` the sources after this one are skipped.
 *
 * @internal
 * @psalm-internal Internal\DLoad\Module\Software
 */
final class ConfigSource implements SoftwareSource
{
    public function __construct(
        private readonly CustomSoftwareRegistry $registry,
    ) {}

    public function collect(SoftwareCollection $collection, callable $next): SoftwareCollection
    {
        $collection = $collection->with(Origin::config(), ...$this->registry->software);

        return $this->registry->overwrite ? $collection : $next($collection);
    }
}
