<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\Repository;

use Internal\DLoad\Module\Repository\Internal\ReleasesCollection;

interface RepositoryInterface
{
    /**
     * @return non-empty-string
     */
    public function getName(): string;

    public function getReleases(): ReleasesCollection;
}
