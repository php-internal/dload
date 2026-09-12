<?php

declare(strict_types=1);

namespace Internal\DLoad\Tests\Unit\Module\Registry\Stub;

use Internal\DLoad\Module\Registry\Record\ReleasePage;
use Internal\DLoad\Module\Registry\Record\ReleaseRecord;
use Internal\DLoad\Module\Registry\ReleaseSource;
use Internal\DLoad\Module\Repository\Exception\ApiException;

/**
 * Source that serves a fixed list of releases in pages and records every page it was asked for.
 *
 * The list is newest first, like a real API listing. A page is "requested" when the generator
 * advances to it, which is exactly when a real source would send the request.
 */
final class ArrayReleaseSource implements ReleaseSource
{
    /**
     * Offsets of the pages that were served, in order.
     *
     * @var list<int>
     */
    public array $served = [];

    /** When set, every page request fails with this exception. */
    public ?\Throwable $failure = null;

    /**
     * @param list<ReleaseRecord> $releases Newest first.
     * @param int<1, max> $perPage
     */
    public function __construct(
        private array $releases,
        private readonly int $perPage = 2,
    ) {}

    /**
     * @param list<non-empty-string> $tags Newest first.
     */
    public static function ofTags(array $tags, int $perPage = 2): self
    {
        return new self(\array_map(static fn(string $tag): ReleaseRecord => new ReleaseRecord($tag, $tag), $tags), $perPage);
    }

    /**
     * Publishes releases on top of the list, as a repository would between two runs.
     *
     * @param non-empty-string ...$tags Newest first.
     */
    public function publish(string ...$tags): void
    {
        $this->releases = [...\array_map(static fn(string $tag): ReleaseRecord => new ReleaseRecord($tag, $tag), $tags), ...$this->releases];
    }

    public function fail(?\Throwable $failure = null): void
    {
        $this->failure = $failure ?? new ApiException('API is unavailable.', 'stub/stub');
    }

    public function pages(int $offset = 0): \Generator
    {
        // Align with a page boundary and skip within the page, like a real paged API
        $page = \intdiv($offset, $this->perPage);
        $skip = $offset % $this->perPage;

        do {
            $this->failure === null or throw $this->failure;

            $this->served[] = $page * $this->perPage;
            $items = \array_slice($this->releases, $page * $this->perPage, $this->perPage);
            $last = ($page + 1) * $this->perPage >= \count($this->releases);

            yield new ReleasePage(\array_slice($items, $skip), $last);

            $skip = 0;
            ++$page;
        } while (!$last);
    }
}
