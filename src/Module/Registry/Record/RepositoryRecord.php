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
 * The record is immutable; every change produces a new instance.
 *
 * @psalm-import-type ReleaseArray from ReleaseRecord
 * @psalm-type RepositoryArray = array{
 *     version: int,
 *     repository: array{type: non-empty-string, uri: non-empty-string},
 *     checked_at: int|null,
 *     complete: bool,
 *     software: list<non-empty-string>,
 *     releases: list<ReleaseArray>,
 * }
 */
final class RepositoryRecord
{
    /** Format version of the stored payload; bump when the structure changes incompatibly. */
    public const FORMAT_VERSION = 1;

    /** @var array<non-empty-string, ReleaseRecord> Releases keyed by tag, newest first. */
    private readonly array $releases;

    /**
     * @param int|null $checkedAt Unix timestamp of the last successful check against the source.
     * @param bool $complete Whether the stored releases reach the end of the source listing.
     * @param list<non-empty-string> $software Identifiers of the software packages served from this repository.
     * @param list<ReleaseRecord> $releases Releases newest first.
     */
    public function __construct(
        public readonly RepositoryId $id,
        public readonly ?int $checkedAt = null,
        public readonly bool $complete = false,
        public readonly array $software = [],
        array $releases = [],
    ) {
        $indexed = [];
        foreach ($releases as $release) {
            $indexed[$release->tag] ??= $release;
        }

        $this->releases = $indexed;
    }

    public static function empty(RepositoryId $id): self
    {
        return new self($id);
    }

    /**
     * @param array<array-key, mixed> $data
     * @throws \InvalidArgumentException When the array does not describe a repository record.
     */
    public static function fromArray(array $data): self
    {
        ($data['version'] ?? null) === self::FORMAT_VERSION or throw new \InvalidArgumentException(
            'Unsupported repository record format.',
        );

        $repository = $data['repository'] ?? null;
        $type = \is_array($repository) ? ($repository['type'] ?? null) : null;
        $uri = \is_array($repository) ? ($repository['uri'] ?? null) : null;
        \is_string($type) && $type !== '' && \is_string($uri) && $uri !== '' or throw new \InvalidArgumentException(
            'Repository record requires a repository type and URI.',
        );

        $releases = [];
        foreach (\is_array($data['releases'] ?? null) ? $data['releases'] : [] as $release) {
            \is_array($release) and $releases[] = ReleaseRecord::fromArray($release);
        }

        /** @var list<non-empty-string> $software */
        $software = \array_values(\array_filter(
            \is_array($data['software'] ?? null) ? $data['software'] : [],
            static fn(mixed $name): bool => \is_string($name) && $name !== '',
        ));

        $checkedAt = $data['checked_at'] ?? null;

        return new self(
            id: new RepositoryId($type, $uri),
            checkedAt: \is_int($checkedAt) ? $checkedAt : null,
            complete: (bool) ($data['complete'] ?? false),
            software: $software,
            releases: $releases,
        );
    }

    /**
     * @return list<ReleaseRecord> Releases newest first.
     */
    public function releases(): array
    {
        return \array_values($this->releases);
    }

    /**
     * @return int<0, max>
     */
    public function count(): int
    {
        return \count($this->releases);
    }

    /**
     * @param non-empty-string $tag
     */
    public function has(string $tag): bool
    {
        return isset($this->releases[$tag]);
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
     * The fetched releases are the newest ones; they overwrite the stored entries with the same
     * tags (assets may have been attached after the release was created) and the remaining stored
     * releases follow them, so the list stays newest first.
     *
     * @param list<ReleaseRecord> $fetched Newest first.
     */
    public function withHead(array $fetched): self
    {
        return $this->with(releases: [...$fetched, ...$this->releases()]);
    }

    /**
     * Appends older releases loaded on demand; already known tags are ignored.
     *
     * @param list<ReleaseRecord> $fetched
     */
    public function withTail(array $fetched): self
    {
        return $this->with(releases: [...$this->releases(), ...$fetched]);
    }

    public function withCheckedAt(int $checkedAt): self
    {
        return $this->with(checkedAt: $checkedAt);
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
            'releases' => \array_map(static fn(ReleaseRecord $release): array => $release->toArray(), $this->releases()),
        ];
    }

    /**
     * @param list<non-empty-string>|null $software
     * @param list<ReleaseRecord>|null $releases
     */
    private function with(
        ?int $checkedAt = null,
        ?bool $complete = null,
        ?array $software = null,
        ?array $releases = null,
    ): self {
        return new self(
            id: $this->id,
            checkedAt: $checkedAt ?? $this->checkedAt,
            complete: $complete ?? $this->complete,
            software: $software ?? $this->software,
            releases: $releases ?? $this->releases(),
        );
    }
}
