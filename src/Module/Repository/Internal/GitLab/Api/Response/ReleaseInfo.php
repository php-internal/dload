<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\Repository\Internal\GitLab\Api\Response;

use Internal\DLoad\Module\Registry\Record\AssetRecord;
use Internal\DLoad\Module\Registry\Record\ReleaseRecord;

/**
 * GitLab Release Data Transfer Object.
 *
 * @internal
 * @psalm-internal Internal\DLoad\Module\Repository\Internal\GitLab
 */
final class ReleaseInfo
{
    /**
     * @param non-empty-string $name
     * @param non-empty-string $tagName
     * @param list<AssetInfo> $assets
     */
    public function __construct(
        public readonly string $name,
        public readonly string $tagName,
        public readonly \DateTimeImmutable $publishedAt,
        public readonly array $assets,
        public readonly bool $prerelease,
    ) {}

    /**
     * @param array{
     *      name: non-empty-string|null,
     *      tag_name: non-empty-string,
     *      description: null|non-empty-string,
     *      created_at: non-empty-string,
     *      released_at: non-empty-string,
     *      assets: array{
     *          links: list<array{
     *              name: non-empty-string,
     *              url: non-empty-string,
     *              direct_asset_url?: non-empty-string,
     *              link_type: non-empty-string,
     *          }>
     *      },
     *      upcoming_release: bool
     * } $data
     */
    public static function fromApiResponse(array $data): self
    {
        $assets = [];
        foreach ($data['assets']['links'] as $assetData) {
            $assets[] = AssetInfo::fromApiResponse($assetData);
        }

        return new self(
            name: $data['name'] ?? $data['tag_name'],
            tagName: $data['tag_name'],
            publishedAt: new \DateTimeImmutable($data['released_at']),
            assets: $assets,
            prerelease: $data['upcoming_release'],
        );
    }

    /**
     * Maps the release into the provider-neutral registry record.
     */
    public function toRecord(): ReleaseRecord
    {
        return new ReleaseRecord(
            tag: $this->tagName,
            name: $this->name,
            publishedAt: $this->publishedAt,
            prerelease: $this->prerelease,
            assets: \array_map(static fn(AssetInfo $asset): AssetRecord => $asset->toRecord(), $this->assets),
        );
    }
}
