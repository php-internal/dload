<?php

declare(strict_types=1);

namespace Internal\DLoad\Tests\Unit\Module\Repository\Internal\GitLab\Stub;

use Internal\DLoad\Tests\Unit\Module\Repository\Stub\ResponseStub;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * HTTP client stub that serves a paginated GitLab releases list.
 *
 * Records every request it receives, so a test can assert not only what the caller got back,
 * but how many requests it took to get there. Like the real API, it honours the `page` and
 * `per_page` query parameters.
 */
final class PagedClientStub implements ClientInterface
{
    /**
     * Query string of every received request, in order.
     *
     * @var list<string>
     */
    public array $requests = [];

    /**
     * @param int<1, max> $pages Number of pages the list is split into when 100 releases are requested per page.
     * @param int<1, max> $releasesPerPage Number of releases on every such page.
     */
    public function __construct(
        private readonly int $pages = 1,
        private readonly int $releasesPerPage = 100,
    ) {}

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $query = $request->getUri()->getQuery();
        $this->requests[] = $query;

        $page = self::pageOf($query);
        $perPage = self::perPageOf($query);
        $all = $this->allReleases();

        // Serve the slice the real API would serve for the requested page size
        $releases = \array_slice($all, ($page - 1) * $perPage, $perPage);
        if ($releases === []) {
            return new ResponseStub(200, [], '[]');
        }

        $headers = $page * $perPage < \count($all)
            ? ['link' => [\sprintf('<https://gitlab.com/api/v4/projects/1/releases?page=%d>; rel="next"', $page + 1)]]
            : [];

        return new ResponseStub(200, $headers, \json_encode($releases));
    }

    /**
     * Page number of every received request, in order.
     *
     * @return list<int>
     */
    public function requestedPages(): array
    {
        return \array_map(self::pageOf(...), $this->requests);
    }

    private static function pageOf(string $query): int
    {
        \parse_str($query, $params);

        return (int) ($params['page'] ?? 1);
    }

    private static function perPageOf(string $query): int
    {
        \parse_str($query, $params);

        return \max(1, (int) ($params['per_page'] ?? 20));
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function allReleases(): array
    {
        $releases = [];
        $total = $this->pages * $this->releasesPerPage;

        for ($i = 1; $i <= $total; $i++) {
            $tag = \sprintf('v1.0.%d', $i);
            $releases[] = [
                'name' => $tag,
                'tag_name' => $tag,
                'description' => 'Release ' . $tag,
                'created_at' => '2024-01-01T00:00:00Z',
                'released_at' => '2024-01-01T00:00:00Z',
                'assets' => ['links' => []],
                'upcoming_release' => false,
            ];
        }

        return $releases;
    }
}
