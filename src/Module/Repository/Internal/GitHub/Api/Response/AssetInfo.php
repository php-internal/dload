<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\Repository\Internal\GitHub\Api\Response;

use Internal\DLoad\Module\Registry\Record\AssetRecord;

/**
 * GitHub Asset Data Transfer Object.
 *
 * @internal
 * @psalm-internal Internal\DLoad\Module\Repository\Internal\GitHub
 */
final class AssetInfo
{
    /**
     * @param non-empty-string $name
     * @param non-empty-string $downloadUrl
     * @param int<0, max> $size
     * @param non-empty-string $contentType
     * @param non-empty-string|null $digest Checksum as `sha256:<hex>`; GitHub reports it for assets uploaded since 2025.
     */
    public function __construct(
        public readonly string $name,
        public readonly string $downloadUrl,
        public readonly int $size,
        public readonly string $contentType,
        public readonly ?string $digest = null,
    ) {}

    /**
     * @param array{
     *     name: string,
     *     browser_download_url: string,
     *     size: int,
     *     content_type: string,
     *     digest?: string|null
     * } $data
     */
    public static function fromApiResponse(array $data): self
    {
        $digest = $data['digest'] ?? null;

        return new self(
            name: $data['name'],
            downloadUrl: $data['browser_download_url'],
            size: $data['size'],
            contentType: $data['content_type'],
            digest: $digest === '' ? null : $digest,
        );
    }

    /**
     * Maps the asset into the provider-neutral registry record.
     */
    public function toRecord(): AssetRecord
    {
        return new AssetRecord(
            name: $this->name,
            uri: $this->downloadUrl,
            size: $this->size,
            contentType: $this->contentType,
            digest: $this->digest,
        );
    }
}
