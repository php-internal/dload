<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\Downloader\Internal\AssetSelection\Rule;

use Internal\DLoad\Module\Downloader\Internal\AssetSelection\AssetRule;
use Internal\DLoad\Module\Downloader\Internal\AssetSelection\Candidate;
use Internal\DLoad\Module\Downloader\Internal\AssetSelection\Selection;

/**
 * Removes assets whose name does not match the repository asset pattern.
 *
 * @internal
 * @psalm-internal Internal\DLoad\Module\Downloader
 */
final class NamePatternRule implements AssetRule
{
    public function select(Selection $selection, callable $next): Selection
    {
        $pattern = $selection->assetPattern;

        return $next($selection->remove(
            static fn(Candidate $candidate): bool => @\preg_match(
                $pattern,
                $candidate->asset->getName(),
                flags: \PREG_NO_ERROR,
            ) !== 1,
        ));
    }
}
