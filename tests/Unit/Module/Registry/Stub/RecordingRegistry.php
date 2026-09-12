<?php

declare(strict_types=1);

namespace Internal\DLoad\Tests\Unit\Module\Registry\Stub;

use Internal\DLoad\Module\Registry\ReleaseSource;
use Internal\DLoad\Module\Registry\RepositoryId;
use Internal\DLoad\Module\Registry\VersionRegistry;

/**
 * Registry that stores nothing but records what it was told, so a test can assert how the
 * downloader talks to it.
 */
final class RecordingRegistry implements VersionRegistry
{
    /** @var list<array{non-empty-string, string}> Software attached, as `[software, repository id]`. */
    public array $attached = [];

    /** @var list<array{string, non-empty-string}> Releases forgotten, as `[repository id, tag]`. */
    public array $forgotten = [];

    public function releases(RepositoryId $id, ReleaseSource $source): \Generator
    {
        foreach ($source->pages() as $page) {
            yield $page->releases;
        }
    }

    public function attach(string $software, RepositoryId $id): void
    {
        $this->attached[] = [$software, (string) $id];
    }

    public function forget(RepositoryId $id, string $tag): void
    {
        $this->forgotten[] = [(string) $id, $tag];
    }
}
