<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\Registry\Record;

/**
 * Release of a repository as stored in the version registry.
 *
 * The record is provider-neutral: GitHub, GitLab and any other source map their own payloads
 * into it, and the repository implementations build their release objects back from it.
 *
 * @psalm-import-type AssetArray from AssetRecord
 * @psalm-type ReleaseArray = array{
 *     tag: non-empty-string,
 *     name: non-empty-string,
 *     published_at?: string|null,
 *     prerelease?: bool,
 *     assets?: list<AssetArray>,
 * }
 */
final class ReleaseRecord
{
    /**
     * @param non-empty-string $tag Tag the release was made from; identifies the release within a repository.
     * @param non-empty-string $name Human-readable release name.
     * @param list<AssetRecord> $assets
     */
    public function __construct(
        public readonly string $tag,
        public readonly string $name,
        public readonly ?\DateTimeImmutable $publishedAt = null,
        public readonly bool $prerelease = false,
        public readonly array $assets = [],
    ) {}

    /**
     * @param array<array-key, mixed> $data
     * @throws \InvalidArgumentException When the array does not describe a release.
     */
    public static function fromArray(array $data): self
    {
        $tag = $data['tag'] ?? null;
        \is_string($tag) && $tag !== '' or throw new \InvalidArgumentException('Release record requires a non-empty `tag`.');

        $name = $data['name'] ?? null;
        \is_string($name) && $name !== '' or $name = $tag;

        $publishedAt = $data['published_at'] ?? null;
        $assets = [];
        foreach (\is_array($data['assets'] ?? null) ? $data['assets'] : [] as $asset) {
            \is_array($asset) and $assets[] = AssetRecord::fromArray($asset);
        }

        return new self(
            tag: $tag,
            name: $name,
            publishedAt: \is_string($publishedAt) && $publishedAt !== '' ? new \DateTimeImmutable($publishedAt) : null,
            prerelease: (bool) ($data['prerelease'] ?? false),
            assets: $assets,
        );
    }

    /**
     * @return ReleaseArray
     */
    public function toArray(): array
    {
        return [
            'tag' => $this->tag,
            'name' => $this->name,
            'published_at' => $this->publishedAt?->format(\DateTimeInterface::ATOM),
            'prerelease' => $this->prerelease,
            'assets' => \array_map(static fn(AssetRecord $asset): array => $asset->toArray(), $this->assets),
        ];
    }
}
