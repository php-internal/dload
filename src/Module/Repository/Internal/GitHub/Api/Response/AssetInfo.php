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
     */
    public function __construct(
        public readonly string $name,
        public readonly string $downloadUrl,
        public readonly int $size,
        public readonly string $contentType,
    ) {}

    /**
     * @param array{
     *     name: string,
     *     browser_download_url: string,
     *     size: int,
     *     content_type: string
     * } $data
     */
    public static function fromApiResponse(array $data): self
    {
        return new self(
            name: $data['name'],
            downloadUrl: $data['browser_download_url'],
            size: $data['size'],
            contentType: $data['content_type'],
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
        );
    }
}
