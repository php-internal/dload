<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\Software\Internal\Source;

use Internal\DLoad\Module\Config\Schema\CustomSoftwareRegistry;
use Internal\DLoad\Module\Software\ComposerPackages;
use Internal\DLoad\Module\Software\Internal\SoftwareSource;
use Internal\DLoad\Module\Software\SoftwareCollection;

/**
 * Adds the software declared by Composer packages of the project.
 *
 * Skipped with `<registry from-packages="false">`.
 *
 * @internal
 * @psalm-internal Internal\DLoad\Module\Software
 */
final class PackageSource implements SoftwareSource
{
    public function __construct(
        private readonly CustomSoftwareRegistry $registry,
        private readonly ComposerPackages $packages,
    ) {}

    public function collect(SoftwareCollection $collection, callable $next): SoftwareCollection
    {
        if ($this->registry->fromPackages) {
            foreach ($this->packages->entries() as $entry) {
                $collection = $collection->with($entry->origin, $entry->software);
            }
        }

        return $next($collection);
    }
}
