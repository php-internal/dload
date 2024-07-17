<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\Repository;

use Internal\DLoad\Module\Environment\Architecture;
use Internal\DLoad\Module\Environment\OperatingSystem;

interface AssetInterface
{
    public function getRelease(): ReleaseInterface;

    /**
     * @return non-empty-string
     */
    public function getName(): string;

    /**
     * @return non-empty-string
     */
    public function getUri(): string;

    public function getOperatingSystem(): ?OperatingSystem;

    public function getArchitecture(): ?Architecture;
}
