<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\Repository\Internal\GitLab\Api\Response;

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
     *      name: string|null,
     *      tag_name: string,
     *      released_at: string,
     *     assets: array{
     *         links: list<array{
     *          name: string,
     *          url: string,
     *          direct_asset_url: string,
     *        }>
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
}
