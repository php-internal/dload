<?php

declare(strict_types=1);

namespace Internal\DLoad\Tests\Unit\Module\Downloader\Stub;

use Internal\DLoad\Module\Repository\Collection\ReleasesCollection;
use Internal\DLoad\Module\Repository\Repository;

/**
 * Repository whose release lookup fails, to model an API error surfacing from {@see getReleases()}.
 */
final class ThrowingRepositoryStub implements Repository
{
    public function __construct(
        private readonly string $name,
        private readonly \Throwable $failure,
    ) {}

    public function getName(): string
    {
        return $this->name;
    }

    public function getReleases(): ReleasesCollection
    {
        throw $this->failure;
    }
}
