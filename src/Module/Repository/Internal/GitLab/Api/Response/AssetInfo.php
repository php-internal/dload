<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\Repository\Internal\GitLab\Api\Response;

use Internal\DLoad\Module\Registry\Record\AssetRecord;

/**
 * GitLab Asset Data Transfer Object.
 *
 * @internal
 * @psalm-internal Internal\DLoad\Module\Repository\Internal\GitLab
 */
final class AssetInfo
{
    /**
     * @param non-empty-string $name
     * @param non-empty-string $downloadUrl
     * @param non-empty-string|null $linkType
     */
    public function __construct(
        public readonly string $name,
        public readonly string $downloadUrl,
        public readonly ?string $linkType = null,
    ) {}

    /**
     * @param array{
     *     name: non-empty-string,
     *     url: non-empty-string,
     *     direct_asset_url?: non-empty-string,
     *     link_type: non-empty-string,
     * } $data
     */
    public static function fromApiResponse(array $data): self
    {
        return new self(
            name: $data['name'],
            downloadUrl: $data['direct_asset_url'] ?? $data['url'],
            linkType: $data['link_type'] ?? null,
        );
    }

    /**
     * Maps the asset into the provider-neutral registry record.
     */
    public function toRecord(): AssetRecord
    {
        return new AssetRecord(name: $this->name, uri: $this->downloadUrl);
    }
}
