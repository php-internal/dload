<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\Registry\Record;

use Internal\DLoad\Module\Registry\RepositoryId;

/**
 * Everything the version registry knows about one repository.
 *
 * Releases are kept newest first, exactly in the order the source lists them, and they form
 * a contiguous prefix of that listing: the registry only ever extends the list at the head (new
 * releases found on a check) or at the tail (older releases loaded on demand). `complete` tells
 * whether the tail has reached the end of the listing.
 *
 * The list is split into segments of at most `SEGMENT_SIZE` releases, so a repository with
 * thousands of releases costs one small index read plus the segments the run actually iterates.
 * New releases are packed into the neighbouring segment while it has room and start a new one
 * when it is full. The tags of every segment live in the index, so counting and membership never
 * load a segment.
 *
 * The record is immutable; every change produces a new instance.
 *
 * @psalm-type RepositoryArray = array{
 *     version: int,
 *     repository: array{type: non-empty-string, uri: non-empty-string},
 *     checked_at: int|null,
 *     complete: bool,
 *     software: list<non-empty-string>,
 *     segments: list<array{key: non-empty-string, tags: list<non-empty-string>}>,
 * }
 *
 * @internal
 */
final class RepositoryRecord
{
    /** Format version of the stored payload; bump when the structure changes incompatibly. */
    public const FORMAT_VERSION = 2;

    /** Greatest number of releases a segment holds. */
    public const SEGMENT_SIZE = 100;

    /** @var list<ReleaseSegment> Newest first. */
    public readonly array $segments;

    /** @var array<non-empty-string, int> Segment position of every stored tag. */
    private readonly array $index;

    /**
     * @param int|null $checkedAt Unix timestamp of the last successful check against the source.
     * @param bool $complete Whether the stored releases reach the end of the source listing.
     * @param list<non-empty-string> $software Identifiers of the software packages served from this repository.
     * @param list<ReleaseSegment> $segments Newest first.
     */
    public function __construct(
        public readonly RepositoryId $id,
        public readonly ?int $checkedAt = null,
        public readonly bool $complete = false,
        public readonly array $software = [],
        array $segments = [],
    ) {
        $index = [];
        foreach ($segments as $position => $segment) {
            foreach ($segment->tags as $tag) {
                $index[$tag] ??= $position;
            }
        }

        $this->segments = $segments;
        $this->index = $index;
    }

    public static function empty(RepositoryId $id): self
    {
        return new self($id);
    }

    /**
     * Restores the index of a record; the releases of every segment come through the loader.
     *
     * @param array<array-key, mixed> $data
     * @param \Closure(non-empty-string): list<ReleaseRecord> $loader Reads the releases of a segment by its key.
     * @throws \InvalidArgumentException When the array does not describe a repository record.
     */
    public static function fromArray(array $data, \Closure $loader): self
    {
        ($data['version'] ?? null) === self::FORMAT_VERSION or throw new \InvalidArgumentException(
            'Unsupported repository record format.',
        );

        /** @var mixed $repository */
        $repository = $data['repository'] ?? null;
        /** @var mixed $type */
        $type = \is_array($repository) ? ($repository['type'] ?? null) : null;
        /** @var mixed $uri */
        $uri = \is_array($repository) ? ($repository['uri'] ?? null) : null;
        \is_string($type) && $type !== '' && \is_string($uri) && $uri !== '' or throw new \InvalidArgumentException(
            'Repository record requires a repository type and URI.',
        );

        $segments = [];
        /** @var mixed $segment */
        foreach (\is_array($data['segments'] ?? null) ? $data['segments'] : [] as $segment) {
            \is_array($segment) or throw new \InvalidArgumentException('Repository record segment must be an object.');

            /** @var mixed $key */
            $key = $segment['key'] ?? null;
            \is_string($key) && $key !== '' or throw new \InvalidArgumentException('Repository record segment requires a `key`.');

            $tags = [];
            /** @var mixed $tag */
            foreach (\is_array($segment['tags'] ?? null) ? $segment['tags'] : [] as $tag) {
                \is_string($tag) && $tag !== '' or throw new \InvalidArgumentException('Repository record segment tags must be non-empty strings.');
                $tags[] = $tag;
            }

            $tags === [] or $segments[] = ReleaseSegment::stored($key, $tags, static fn(): array => $loader($key));
        }

        $software = [];
        /** @var mixed $name */
        foreach (\is_array($data['software'] ?? null) ? $data['software'] : [] as $name) {
            \is_string($name) && $name !== '' and $software[] = $name;
        }

        /** @var mixed $checkedAt */
        $checkedAt = $data['checked_at'] ?? null;

        return new self(
            id: new RepositoryId($type, $uri),
            checkedAt: \is_int($checkedAt) ? $checkedAt : null,
            complete: (bool) ($data['complete'] ?? false),
            software: $software,
            segments: $segments,
        );
    }

    /**
     * Releases segment by segment, newest first; a segment is read from storage when reached.
     *
     * @return \Generator<int, list<ReleaseRecord>, mixed, void>
     * @throws \RuntimeException When a segment cannot be read from storage.
     */
    public function pages(): \Generator
    {
        foreach ($this->segments as $segment) {
            yield $segment->releases();
        }
    }

    /**
     * Every release, newest first; reads every segment.
     *
     * @return list<ReleaseRecord>
     * @throws \RuntimeException When a segment cannot be read from storage.
     */
    public function releases(): array
    {
        $releases = [];
        foreach ($this->pages() as $page) {
            $releases = [...$releases, ...$page];
        }

        return $releases;
    }

    /**
     * @return int<0, max>
     */
    public function count(): int
    {
        return \count($this->index);
    }

    /**
     * @param non-empty-string $tag
     */
    public function has(string $tag): bool
    {
        return isset($this->index[$tag]);
    }

    /**
     * Whether the last check is older than the given number of seconds, or never happened.
     *
     * @param int<0, max> $ttl
     */
    public function isStale(int $now, int $ttl): bool
    {
        return $this->checkedAt === null || $now - $this->checkedAt > $ttl;
    }

    /**
     * Replaces the head of the list with freshly fetched releases.
     *
     * The fetched releases are the newest ones, and sources only ever add releases at the top,
     * so from the first stored release they contain onwards both lists walk the same listing
     * positions. A stored release missing from the fetched span at its position was deleted
     * upstream and is dropped: keeping it would inflate `count()`, which the registry uses as
     * the offset for loading the tail. Stored releases beyond the span are kept, and when the
     * fetched releases contain no stored one they are the whole listing.
     *
     * @param list<ReleaseRecord> $fetched Newest first.
     */
    public function withHead(array $fetched): self
    {
        $fetched = self::unique($fetched);
        $fetchedTags = \array_fill_keys(\array_map(static fn(ReleaseRecord $release): string => $release->tag, $fetched), true);

        // Flat view of the stored tags with the segment each one belongs to
        $stored = [];
        foreach ($this->segments as $position => $segment) {
            foreach ($segment->tags as $tag) {
                $stored[] = [$tag, $position];
            }
        }

        $overlap = null;
        foreach ($stored as $offset => [$tag]) {
            if (isset($fetchedTags[$tag])) {
                $overlap = $offset;
                break;
            }
        }

        if ($overlap === null) {
            return $this->with(segments: self::pack($fetched, [], $this->nextKey()));
        }

        // Listing positions the fetched releases still cover, counting from the overlap
        $spanned = 0;
        foreach ($fetched as $position => $release) {
            if ($release->tag === $stored[$overlap][0]) {
                $spanned = \count($fetched) - $position;
                break;
            }
        }

        $keepFrom = \count($stored);
        foreach (\array_slice($stored, $overlap, preserve_keys: true) as $offset => [$tag]) {
            if ($spanned <= 0) {
                $keepFrom = $offset;
                break;
            }

            isset($fetchedTags[$tag]) and --$spanned;
        }

        // The segment holding the first kept release is split unless the release opens it;
        // the segments after it stay as they are
        $loose = $fetched;
        $following = [];
        if ($keepFrom < \count($stored)) {
            [$tag, $position] = $stored[$keepFrom];
            $segment = $this->segments[$position];
            $start = (int) \array_search($tag, $segment->tags, true);
            $start === 0 or $loose = [...$loose, ...\array_slice($segment->releases(), $start)];
            $following = \array_slice($this->segments, $start === 0 ? $position : $position + 1);
        }

        return $this->with(segments: self::pack($loose, $following, $this->nextKey()));
    }

    /**
     * Appends older releases loaded on demand; already known tags are ignored.
     *
     * The last segment is filled up before a new one starts.
     *
     * @param list<ReleaseRecord> $fetched Newest first.
     */
    public function withTail(array $fetched): self
    {
        $new = \array_values(\array_filter(
            self::unique($fetched),
            fn(ReleaseRecord $release): bool => !$this->has($release->tag),
        ));
        if ($new === []) {
            return $this;
        }

        $segments = $this->segments;
        $last = \array_pop($segments);
        if ($last === null || $last->count() >= self::SEGMENT_SIZE) {
            $last === null or $segments[] = $last;

            return $this->with(segments: [...$segments, ...self::chunk($new, $this->nextKey())]);
        }

        return $this->with(segments: [
            ...$segments,
            ...self::chunk([...$last->releases(), ...$new], $this->nextKey(), $last->key),
        ]);
    }

    public function withCheckedAt(int $checkedAt): self
    {
        return $this->with(checkedAt: $checkedAt);
    }

    /**
     * Forgets the last check, so the record counts as stale until the source is asked again.
     */
    public function withoutCheck(): self
    {
        return new self(
            id: $this->id,
            checkedAt: null,
            complete: $this->complete,
            software: $this->software,
            segments: $this->segments,
        );
    }

    /**
     * Drops a release; an unknown tag leaves the record as it is.
     *
     * @param non-empty-string $tag
     */
    public function withoutRelease(string $tag): self
    {
        if (!$this->has($tag)) {
            return $this;
        }

        $position = $this->index[$tag];
        $segments = $this->segments;
        $remaining = \array_values(\array_filter(
            $segments[$position]->releases(),
            static fn(ReleaseRecord $release): bool => $release->tag !== $tag,
        ));

        $remaining === []
            ? \array_splice($segments, $position, 1)
            : $segments[$position] = ReleaseSegment::fresh($segments[$position]->key, $remaining);

        return $this->with(segments: $segments);
    }

    public function withComplete(bool $complete): self
    {
        return $this->with(complete: $complete);
    }

    /**
     * @param non-empty-string $software
     */
    public function withSoftware(string $software): self
    {
        return \in_array($software, $this->software, true)
            ? $this
            : $this->with(software: [...$this->software, $software]);
    }

    /**
     * The record as the storage holds it now: no segment is dirty any more.
     */
    public function persisted(): self
    {
        return $this->with(segments: \array_map(static fn(ReleaseSegment $segment): ReleaseSegment => $segment->persisted(), $this->segments));
    }

    /**
     * The index of the record; the releases of the segments are stored separately.
     *
     * @return RepositoryArray
     */
    public function toArray(): array
    {
        return [
            'version' => self::FORMAT_VERSION,
            'repository' => ['type' => $this->id->type, 'uri' => $this->id->uri],
            'checked_at' => $this->checkedAt,
            'complete' => $this->complete,
            'software' => $this->software,
            'segments' => \array_map(
                static fn(ReleaseSegment $segment): array => ['key' => $segment->key, 'tags' => $segment->tags],
                $this->segments,
            ),
        ];
    }

    /**
     * Packs loose releases into segments in front of the given ones.
     *
     * The short remainder goes first, where the next check adds its releases, so the head segment
     * fills up over several checks and the full segments behind it are never rewritten. When the
     * remainder and the first following segment fit into one, they are joined.
     *
     * @param list<ReleaseRecord> $loose Newest first.
     * @param list<ReleaseSegment> $following Segments that stay as they are.
     * @param int<1, max> $nextKey
     * @return list<ReleaseSegment>
     */
    private static function pack(array $loose, array $following, int $nextKey): array
    {
        if ($loose === []) {
            return $following;
        }

        $remainder = \count($loose) % self::SEGMENT_SIZE;
        $first = $following[0] ?? null;
        if ($first !== null && $remainder > 0 && $remainder + $first->count() <= self::SEGMENT_SIZE) {
            $loose = [...$loose, ...$first->releases()];
            \array_shift($following);
            $remainder = \count($loose) % self::SEGMENT_SIZE;
        }

        $head = [];
        if ($remainder > 0) {
            /** @var non-empty-list<ReleaseRecord> $partial */
            $partial = \array_slice($loose, 0, $remainder);
            $head[] = ReleaseSegment::fresh(self::key($nextKey++), $partial);
        }

        return [...$head, ...self::chunk(\array_slice($loose, $remainder), $nextKey), ...$following];
    }

    /**
     * Splits releases into segments of at most `SEGMENT_SIZE`, keying them from `$nextKey` on.
     *
     * @param list<ReleaseRecord> $releases
     * @param int<1, max> $nextKey
     * @param non-empty-string|null $reuseKey Key for the first segment, when it replaces an existing one.
     * @return list<ReleaseSegment>
     */
    private static function chunk(array $releases, int $nextKey, ?string $reuseKey = null): array
    {
        $segments = [];
        foreach (\array_chunk($releases, self::SEGMENT_SIZE) as $chunk) {
            $segments[] = ReleaseSegment::fresh($reuseKey ?? self::key($nextKey++), $chunk);
            $reuseKey = null;
        }

        return $segments;
    }

    /**
     * @param int<1, max> $number
     * @return non-empty-string
     */
    private static function key(int $number): string
    {
        /** @var non-empty-string */
        return \sprintf('%04d', $number);
    }

    /**
     * Drops the releases repeating a tag seen before, so no tag lands in two segments.
     *
     * @param list<ReleaseRecord> $releases
     * @return list<ReleaseRecord>
     */
    private static function unique(array $releases): array
    {
        $seen = [];
        $unique = [];
        foreach ($releases as $release) {
            isset($seen[$release->tag]) or $unique[] = $release;
            $seen[$release->tag] = true;
        }

        return $unique;
    }

    /**
     * @return int<1, max>
     */
    private function nextKey(): int
    {
        $max = 0;
        foreach ($this->segments as $segment) {
            $max = \max($max, (int) $segment->key);
        }

        return $max + 1;
    }

    /**
     * @param list<non-empty-string>|null $software
     * @param list<ReleaseSegment>|null $segments
     */
    private function with(
        ?int $checkedAt = null,
        ?bool $complete = null,
        ?array $software = null,
        ?array $segments = null,
    ): self {
        return new self(
            id: $this->id,
            checkedAt: $checkedAt ?? $this->checkedAt,
            complete: $complete ?? $this->complete,
            software: $software ?? $this->software,
            segments: $segments ?? $this->segments,
        );
    }
}
