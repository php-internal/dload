<?php

declare(strict_types=1);

namespace Internal\DLoad\Tests\Unit\Module\Downloader\Stub;

use Internal\DLoad\Module\Common\Architecture;
use Internal\DLoad\Module\Common\OperatingSystem;
use Internal\DLoad\Module\Repository\AssetInterface;
use Internal\DLoad\Module\Repository\Exception\AssetNotFoundException;
use Internal\DLoad\Module\Repository\ReleaseInterface;

/**
 * Asset whose download answers "not found", as a deleted release does.
 */
final class GoneAssetStub implements AssetInterface
{
    /**
     * @param non-empty-string $name
     */
    public function __construct(
        private readonly ReleaseInterface $release,
        private readonly string $name,
    ) {}

    public function getRelease(): ReleaseInterface
    {
        return $this->release;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getUri(): string
    {
        return 'https://github.com/owner/repo/releases/download/' . $this->release->getName() . '/' . $this->name;
    }

    public function getOperatingSystem(): ?OperatingSystem
    {
        return null;
    }

    public function getArchitecture(): ?Architecture
    {
        return null;
    }

    public function download(): \Traversable
    {
        throw new AssetNotFoundException('GitHub asset is no longer available: HTTP 404 for ' . $this->getUri(), 'owner/repo');

        /** @psalm-suppress UnevaluatedCode */
        yield '';
    }
}
