<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\Downloader\Internal\AssetSelection\Rule;

use Internal\DLoad\Module\Downloader\Internal\AssetSelection\AssetRule;
use Internal\DLoad\Module\Downloader\Internal\AssetSelection\Candidate;
use Internal\DLoad\Module\Downloader\Internal\AssetSelection\Selection;

/**
 * Prefers the plain build: the fewer extra tokens in the name, the better.
 *
 * Variants like `-baseline`, `-profile` or `-debug` add a token to the name of the plain build,
 * so no list of variant names is needed. Tokens shared by all the assets, like the tool name,
 * do not change the order.
 *
 * @internal
 * @psalm-internal Internal\DLoad\Module\Downloader
 */
final class ExtrasRule implements AssetRule
{
    public function select(Selection $selection, callable $next): Selection
    {
        return $next($selection->rank('extras', static fn(Candidate $candidate): int => \count($candidate->name->extras)));
    }
}
