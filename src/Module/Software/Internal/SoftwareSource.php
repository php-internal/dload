<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\Software\Internal;

use Internal\DLoad\Module\Common\Pipeline\Interceptor;
use Internal\DLoad\Module\Software\SoftwareCollection;

/**
 * Step of the software collection pipeline: adds the definitions of one source.
 *
 * Priority between sources comes from {@see \Internal\DLoad\Module\Software\OriginKind}, not from
 * the order of the steps. A step that does not call `$next` drops all the sources after it.
 *
 * @extends Interceptor<SoftwareCollection, SoftwareCollection>
 *
 * @internal
 * @psalm-internal Internal\DLoad\Module\Software
 */
interface SoftwareSource extends Interceptor
{
    /**
     * @param callable(SoftwareCollection): SoftwareCollection $next
     */
    public function collect(SoftwareCollection $collection, callable $next): SoftwareCollection;
}
