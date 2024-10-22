<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\Repository\Internal;

use Internal\DLoad\Module\Common\Architecture;
use Internal\DLoad\Module\Common\OperatingSystem;
use Internal\DLoad\Module\Repository\AssetInterface;
use Internal\DLoad\Module\Repository\ReleaseInterface;

/**
 * @internal
 * @psalm-internal Internal\DLoad\Module\Repository
 */
abstract class Asset implements AssetInterface
{
    /**
     * @param non-empty-string $name
     * @param non-empty-string $uri
     */
    public function __construct(
        protected ReleaseInterface $release,
        protected string $name,
        protected string $uri,
        protected ?OperatingSystem $os,
        protected ?Architecture $arch,
    ) {}

    public function getRelease(): ReleaseInterface
    {
        return $this->release;
    }

    public function getOperatingSystem(): ?OperatingSystem
    {
        return $this->os;
    }

    public function getArchitecture(): ?Architecture
    {
        return $this->arch;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getUri(): string
    {
        return $this->uri;
    }
}
