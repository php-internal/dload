<?php

declare(strict_types=1);

namespace Internal\DLoad\Tests\Unit\Module\Repository\Internal;

use Internal\DLoad\Module\Registry\Record\ReleasePage;
use Internal\DLoad\Module\Registry\Record\ReleaseRecord;
use Internal\DLoad\Module\Repository\Internal\PagedReleaseSource;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Lifecycle\BeforeTest;
use Testo\Test;

#[Covers(PagedReleaseSource::class)]
final class PagedReleaseSourceTest
{
    /**
     * Pages requested from the API, in order.
     *
     * @var list<int>
     */
    private array $requested = [];

    #[Test]
    public function pagesStartWithTheOneHoldingTheOffset(): void
    {
        $source = $this->source(total: 250, perPage: 100);

        $pages = \iterator_to_array($source->pages(150), false);

        Assert::same($this->requested, [2, 3]);
        Assert::same(self::tagsOf($pages[0]), self::tags(100, 200));
        Assert::same($pages[0]->offset, 100);
        Assert::same(self::tagsOf($pages[1]), self::tags(200, 250));
        Assert::same($pages[1]->offset, 200);
        Assert::true($pages[1]->last);
    }

    #[Test]
    public function pagesAreRequestedOnlyWhenReached(): void
    {
        $source = $this->source(total: 250, perPage: 100);

        $pages = $source->pages();
        Assert::same($this->requested, []);

        $pages->current();
        Assert::same($this->requested, [1]);

        $pages->next();
        Assert::same($this->requested, [1, 2]);
    }

    #[Test]
    public function listingEndingBeforeTheOffsetStartsWithItsLastPage(): void
    {
        $source = $this->source(total: 250, perPage: 100);

        $pages = \iterator_to_array($source->pages(450), false);

        Assert::same($this->requested, [5, 4, 3]);
        Assert::count($pages, 1);
        Assert::same($pages[0]->offset, 200);
        Assert::true($pages[0]->last);
    }

    #[Test]
    public function emptyListingYieldsNoPage(): void
    {
        $source = $this->source(total: 0, perPage: 100);

        Assert::same(\iterator_to_array($source->pages(), false), []);
        Assert::same(\iterator_to_array($source->pages(150), false), []);
        Assert::same($this->requested, [1, 2, 1]);
    }

    #[Test]
    public function smallFirstPageTellsThePageSize(): void
    {
        // The server puts two releases on a page whatever is asked for
        $source = $this->source(total: 10, perPage: 100, served: 2);

        $pages = \iterator_to_array($source->pages(5), false);

        // Page 1 was fetched on the assumption of a hundred per page; it showed the real size
        Assert::same($this->requested, [1, 3, 4, 5]);
        Assert::same(self::tagsOf($pages[0]), self::tags(4, 6));
        Assert::same($pages[0]->offset, 4);

        // The size learned serves the next listing of the same source
        $this->requested = [];
        \iterator_to_array($source->pages(8), false);
        Assert::same($this->requested, [5]);
    }

    #[Test]
    public function firstPageShortOfAFewReleasesKeepsThePageSize(): void
    {
        // A release filtered out after paging, not a smaller page
        $source = $this->source(total: 300, perPage: 100, missing: ['v3']);

        $source->pages()->current();
        $pages = \iterator_to_array($source->pages(200), false);

        Assert::same($this->requested, [1, 3]);
        Assert::same($pages[0]->offset, 200);
    }

    #[Test]
    public function shortPageBelowTheFirstOneIsNotTakenForThePageSize(): void
    {
        $source = $this->source(total: 300, perPage: 100, missing: ['v150']);

        $pages = \iterator_to_array($source->pages(200), false);

        Assert::same($this->requested, [3]);
        Assert::same(self::tagsOf($pages[0]), self::tags(200, 300));
    }

    #[Test]
    public function unreadableReleasesHoldTheirPositionsOnThePage(): void
    {
        $source = $this->source(total: 300, perPage: 100, unreadable: ['v102']);

        $page = $source->pages(105)->current();

        Assert::same($page->offset, 100);
        Assert::same($page->skipped, 1);
        Assert::count($page->releases, 99);
    }

    #[BeforeTest]
    protected function prepare(): void
    {
        $this->requested = [];
    }

    /**
     * @return list<non-empty-string>
     */
    private static function tags(int $from, int $to): array
    {
        return $to > $from ? \array_map(static fn(int $i): string => "v$i", \range($from, $to - 1)) : [];
    }

    /**
     * @return list<non-empty-string>
     */
    private static function tagsOf(ReleasePage $page): array
    {
        return \array_map(static fn(ReleaseRecord $release): string => $release->tag, $page->releases);
    }

    /**
     * Source over a listing of `v0`..`v{total-1}`.
     *
     * @param int<0, max> $total
     * @param int<1, max> $perPage Releases asked for per page.
     * @param int<1, max>|null $served Releases the server puts on a page instead.
     * @param list<non-empty-string> $missing Tags filtered out after paging.
     * @param list<non-empty-string> $unreadable Tags the API cannot read.
     */
    private function source(int $total, int $perPage, ?int $served = null, array $missing = [], array $unreadable = []): PagedReleaseSource
    {
        $size = $served ?? $perPage;
        $pages = function (int $page) use ($total, $size, $missing, $unreadable): \Generator {
            while (true) {
                // Like a real API, a page past the end is requested and comes back empty
                $this->requested[] = $page;
                $slice = \array_slice(self::tags(0, $total), ($page - 1) * $size, $size);
                if ($slice === []) {
                    return;
                }

                $releases = [];
                $skipped = 0;
                foreach ($slice as $tag) {
                    if (\in_array($tag, $missing, true)) {
                        continue;
                    }
                    if (\in_array($tag, $unreadable, true)) {
                        ++$skipped;
                        continue;
                    }
                    $releases[] = new ReleaseRecord($tag, $tag);
                }

                $last = $page * $size >= $total;
                yield new ReleasePage($releases, $last, $skipped);

                if ($last) {
                    return;
                }
                ++$page;
            }
        };

        return new PagedReleaseSource($pages, $perPage);
    }
}
