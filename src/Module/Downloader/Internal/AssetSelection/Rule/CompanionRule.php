<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\Downloader\Internal\AssetSelection\Rule;

use Internal\DLoad\Module\Downloader\Internal\AssetSelection\AssetRule;
use Internal\DLoad\Module\Downloader\Internal\AssetSelection\Candidate;
use Internal\DLoad\Module\Downloader\Internal\AssetSelection\Selection;

/**
 * Removes files that accompany a build: checksums, signatures, certificates and SBOMs.
 *
 * They carry the platform of the build they describe, so without this rule they would rank as
 * high as the build itself, and downloading one succeeds, which ends the search.
 *
 * @internal
 * @psalm-internal Internal\DLoad\Module\Downloader
 */
final class CompanionRule implements AssetRule
{
    private const PATTERN = '/(?:\.(?:sha\d*(?:sum)?|md5|asc|sig|minisig|pem|crt|sbom(?:\.json)?|spdx(?:\.json)?|intoto\.jsonl)|(?:^|[-_.])(?:sha\d+sums?|checksums?)(?:\.txt)?)$/i';

    public function select(Selection $selection, callable $next): Selection
    {
        return $next($selection->remove(
            static fn(Candidate $candidate): bool => \preg_match(self::PATTERN, $candidate->asset->getName()) === 1,
        ));
    }
}
