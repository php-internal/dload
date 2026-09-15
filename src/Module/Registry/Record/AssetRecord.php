<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\Registry\Record;

/**
 * Downloadable asset of a release as stored in the version registry.
 *
 * Holds only the metadata needed to pick and fetch the asset later. The download itself still goes
 * through the repository client with its credentials, so the record can be shared freely.
 *
 * @psalm-type AssetArray = array{
 *     name: non-empty-string,
 *     uri: non-empty-string,
 *     size?: int|null,
 *     content_type?: string|null,
 *     digest?: string|null,
 * }
 *
 * @internal
 */
final class AssetRecord
{
    /**
     * @param non-empty-string $name Asset file name.
     * @param non-empty-string $uri Download URI.
     * @param int<0, max>|null $size Size in bytes when the source reports it.
     * @param non-empty-string|null $contentType MIME type when the source reports it.
     * @param non-empty-string|null $digest Content checksum as `<algorithm>:<hex>`, e.g. `sha256:9f86d0…`, when the source reports it.
     */
    public function __construct(
        public readonly string $name,
        public readonly string $uri,
        public readonly ?int $size = null,
        public readonly ?string $contentType = null,
        public readonly ?string $digest = null,
    ) {}

    /**
     * @param array<array-key, mixed> $data
     * @throws \InvalidArgumentException When the array does not describe an asset.
     */
    public static function fromArray(array $data): self
    {
        $name = $data['name'] ?? null;
        $uri = $data['uri'] ?? null;
        \is_string($name) && $name !== '' && \is_string($uri) && $uri !== '' or throw new \InvalidArgumentException(
            'Asset record requires non-empty `name` and `uri`.',
        );

        /** @var mixed $size */
        $size = $data['size'] ?? null;
        /** @var mixed $contentType */
        $contentType = $data['content_type'] ?? null;
        /** @var mixed $digest */
        $digest = $data['digest'] ?? null;

        return new self(
            name: $name,
            uri: $uri,
            size: \is_int($size) && $size >= 0 ? $size : null,
            contentType: \is_string($contentType) && $contentType !== '' ? $contentType : null,
            digest: \is_string($digest) && $digest !== '' ? $digest : null,
        );
    }

    /**
     * @return AssetArray
     */
    public function toArray(): array
    {
        $result = ['name' => $this->name, 'uri' => $this->uri];
        $this->size === null or $result['size'] = $this->size;
        $this->contentType === null or $result['content_type'] = $this->contentType;
        $this->digest === null or $result['digest'] = $this->digest;

        return $result;
    }
}
