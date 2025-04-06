<?php

declare(strict_types=1);

namespace Internal\DLoad\Tests\Unit\Module\Repository\Stub;

use Internal\DLoad\Module\Common\Architecture;
use Internal\DLoad\Module\Common\OperatingSystem;
use Internal\DLoad\Module\Repository\AssetInterface;
use Internal\DLoad\Module\Repository\ReleaseInterface;

/**
 * Test stub implementation of AssetInterface for unit tests.
 */
final class AssetStub implements AssetInterface
{
    /**
     * @param non-empty-string $name
     * @param non-empty-string $uri
     */
    public function __construct(
        private ReleaseInterface $release,
        private string $name,
        private string $uri,
        private ?OperatingSystem $operatingSystem = null,
        private ?Architecture $architecture = null,
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
        return $this->uri;
    }

    public function getOperatingSystem(): ?OperatingSystem
    {
        return $this->operatingSystem;
    }

    public function getArchitecture(): ?Architecture
    {
        return $this->architecture;
    }

    public function download(): \Traversable
    {
        // Simulate a download stream with mock content
        yield 'Mock download content for ' . $this->name;
    }
}
