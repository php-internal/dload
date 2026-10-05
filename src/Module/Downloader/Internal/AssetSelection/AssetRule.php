<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\Downloader\Internal\AssetSelection;

use Internal\DLoad\Module\Common\Pipeline\Interceptor;

/**
 * Step of the asset selection pipeline.
 *
 * A rule ranks the selection before passing it to `$next`: ranks are compared in the order they
 * were added, so the order of the rules in {@see AssetSelector} is the priority of their ranks.
 *
 * @extends Interceptor<Selection, Selection>
 *
 * @internal
 * @psalm-internal Internal\DLoad\Module\Downloader
 */
interface AssetRule extends Interceptor
{
    /**
     * @param callable(Selection): Selection $next
     */
    public function select(Selection $selection, callable $next): Selection;
}
