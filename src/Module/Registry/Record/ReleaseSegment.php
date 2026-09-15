<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\Registry\Record;

/**
 * A consecutive slice of the release list of a repository, stored as one unit.
 *
 * The tags are always known, so the record can count releases and tell whether a tag is stored
 * without touching the releases themselves. The releases are loaded on first use: a segment read
 * from storage carries a loader instead of the data, and a segment that was just assembled from
 * fetched releases carries the data and is `dirty` until the storage has written it.
 *
 * @internal
 */
final class ReleaseSegment
{
    /** @var list<ReleaseRecord>|null */
    private ?array $releases;

    /** @var (\Closure(): list<ReleaseRecord>)|null */
    private ?\Closure $loader;

    /**
     * @param non-empty-string $key Identifier of the segment, unique within the repository record.
     * @param list<non-empty-string> $tags Tags of the releases, newest first.
     * @param list<ReleaseRecord>|null $releases Releases, newest first; `null` when a loader provides them.
     * @param (\Closure(): list<ReleaseRecord>)|null $loader Reads the releases from storage.
     * @param bool $dirty Whether the storage does not hold this content yet.
     */
    private function __construct(
        public readonly string $key,
        public readonly array $tags,
        ?array $releases,
        ?\Closure $loader,
        public readonly bool $dirty,
    ) {
        $this->releases = $releases;
        $this->loader = $loader;
    }

    /**
     * Segment assembled from releases that are not stored yet.
     *
     * @param non-empty-string $key
     * @param non-empty-list<ReleaseRecord> $releases Newest first.
     */
    public static function fresh(string $key, array $releases): self
    {
        return new self(
            key: $key,
            tags: \array_map(static fn(ReleaseRecord $release): string => $release->tag, $releases),
            releases: $releases,
            loader: null,
            dirty: true,
        );
    }

    /**
     * Segment whose releases are read from storage when first needed.
     *
     * @param non-empty-string $key
     * @param non-empty-list<non-empty-string> $tags Newest first.
     * @param \Closure(): list<ReleaseRecord> $loader
     */
    public static function stored(string $key, array $tags, \Closure $loader): self
    {
        return new self(key: $key, tags: $tags, releases: null, loader: $loader, dirty: false);
    }

    /**
     * @return list<ReleaseRecord> Newest first.
     * @throws \RuntimeException When the releases cannot be read from storage.
     */
    public function releases(): array
    {
        if ($this->releases === null) {
            $this->releases = ($this->loader ?? static fn(): array => [])();
            $this->loader = null;
        }

        return $this->releases;
    }

    /**
     * @return int<0, max>
     */
    public function count(): int
    {
        return \count($this->tags);
    }

    /**
     * @param non-empty-string $tag
     */
    public function has(string $tag): bool
    {
        return \in_array($tag, $this->tags, true);
    }

    /**
     * The same segment as the storage holds it now.
     */
    public function persisted(): self
    {
        return $this->dirty
            ? new self(key: $this->key, tags: $this->tags, releases: $this->releases, loader: null, dirty: false)
            : $this;
    }
}
