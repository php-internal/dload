<?php

declare(strict_types=1);

namespace Internal\DLoad\Tests\Unit\Module\Downloader\Stub;

use Internal\DLoad\Module\Common\Architecture;
use Internal\DLoad\Module\Common\OperatingSystem;
use Internal\DLoad\Module\Repository\AssetInterface;
use Internal\DLoad\Module\Repository\ReleaseInterface;

/**
 * Asset with configurable OS/architecture and an optional download failure, so a test can steer
 * which gradual/strict filtering strategy selects it and how its download ends.
 */
final class ConfigurableAssetStub implements AssetInterface
{
    /**
     * @param non-empty-string $name
     * @param \Throwable|null $failure Thrown by {@see download()}; a successful stream when null.
     */
    public function __construct(
        private readonly ReleaseInterface $release,
        private readonly string $name,
        private readonly ?OperatingSystem $operatingSystem = null,
        private readonly ?Architecture $architecture = null,
        private readonly ?\Throwable $failure = null,
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
        return 'https://x/' . $this->name;
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
        if ($this->failure !== null) {
            throw $this->failure;

            /** @psalm-suppress UnevaluatedCode */
            yield '';
        }

        yield 'Mock download content for ' . $this->name;
    }
}
