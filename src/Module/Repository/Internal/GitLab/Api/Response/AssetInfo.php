<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\Repository\Internal\GitLab\Api\Response;

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
     */
    public function __construct(
        public readonly string $name,
        public readonly string $downloadUrl,
    ) {}

    /**
     * @param array{
     *     name: string,
     *     url: string,
     *     direct_asset_url: string,
     * } $data
     */
    public static function fromApiResponse(array $data): self
    {
        return new self(
            name: $data['name'],
            downloadUrl: $data['direct_asset_url'],
        );
    }
}
