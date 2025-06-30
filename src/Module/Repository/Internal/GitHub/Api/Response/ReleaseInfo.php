<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\Repository\Internal\GitHub\Api\Response;

/**
 * GitHub Release Data Transfer Object.
 *
 * @internal
 * @psalm-internal Internal\DLoad\Module\Repository\Internal\GitHub
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
        public readonly bool $draft,
    ) {}

    /**
     * @param array{
     *     name: string|null,
     *     tag_name: string,
     *     published_at: string,
     *     assets: array<array-key, array{
     *         name: string,
     *         browser_download_url: string,
     *         size: int,
     *         content_type: string
     *     }>,
     *     prerelease: bool,
     *     draft: bool
     * } $data
     */
    public static function fromApiResponse(array $data): self
    {
        $assets = [];
        foreach ($data['assets'] as $assetData) {
            $assets[] = AssetInfo::fromApiResponse($assetData);
        }

        return new self(
            name: $data['name'] ?? $data['tag_name'],
            tagName: $data['tag_name'],
            publishedAt: new \DateTimeImmutable($data['published_at']),
            assets: $assets,
            prerelease: $data['prerelease'],
            draft: $data['draft'],
        );
    }
}
