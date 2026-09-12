<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\Registry\Internal;

use Internal\DLoad\Module\Registry\ReleaseSource;
use Internal\DLoad\Module\Registry\RepositoryId;
use Internal\DLoad\Module\Registry\VersionRegistry;

/**
 * Registry that stores nothing: every listing goes straight to the source.
 *
 * Used when the version database is disabled, so callers never have to check whether it is.
 *
 * @internal
 * @psalm-internal Internal\DLoad
 */
final class PassThroughRegistry implements VersionRegistry
{
    public function releases(RepositoryId $id, ReleaseSource $source): \Generator
    {
        foreach ($source->pages() as $page) {
            yield $page->releases;
        }
    }

    public function attach(string $software, RepositoryId $id): void
    {
        // Nothing to record
    }
}
