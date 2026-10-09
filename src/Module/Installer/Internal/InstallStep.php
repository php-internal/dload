<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\Installer\Internal;

use Internal\DLoad\Module\Common\DloadResult;
use Internal\DLoad\Module\Common\Pipeline\Interceptor;
use Internal\DLoad\Module\Installer\Installation;

/**
 * Step of the installation pipeline.
 *
 * A step either installs the download itself and returns without calling `$next`, or passes it on.
 * Downloads no step takes are extracted by {@see RuleExtraction}.
 *
 * @extends Interceptor<Installation, DloadResult>
 *
 * @internal
 * @psalm-internal Internal\DLoad\Module\Installer
 */
interface InstallStep extends Interceptor
{
    /**
     * @param callable(Installation): DloadResult $next
     */
    public function install(Installation $installation, callable $next): DloadResult;
}
