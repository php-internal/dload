<?php

declare(strict_types=1);

namespace Internal\DLoad\Tests\Unit\Module\Repository\Internal\GitHub\Stub;

use Internal\DLoad\Module\Registry\Record\ReleaseRecord;
use Internal\DLoad\Module\Registry\ReleaseSource;
use Internal\DLoad\Module\Registry\RepositoryId;
use Internal\DLoad\Module\Registry\VersionRegistry;

/**
 * Registry whose paging is written out step by step, so a test drives exactly what the repository
 * sees while iterating: each step is either a page to yield or a throwable to raise in its place.
 *
 * A throwable at the first step fails the initial `rewind()`; a later one fails the `next()` that
 * asks for that page.
 */
final class ScriptedRegistryStub implements VersionRegistry
{
    /**
     * @param list<list<ReleaseRecord>|\Throwable> $steps
     */
    public function __construct(
        private readonly array $steps,
    ) {}

    public function releases(RepositoryId $id, ReleaseSource $source): \Generator
    {
        foreach ($this->steps as $step) {
            $step instanceof \Throwable and throw $step;

            yield $step;
        }
    }

    public function attach(RepositoryId $id, string $software): void {}

    public function forget(RepositoryId $id, string $tag): void {}
}
