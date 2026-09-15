<?php

declare(strict_types=1);

namespace Internal\DLoad\Tests\Unit\Module\Repository\Internal\GitHub\Stub;

use Internal\DLoad\Tests\Unit\Module\Repository\Stub\ResponseStub;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * HTTP client stub that serves a paginated GitHub releases list.
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
     * @param int<0, max> $drafts Draft releases listed on top, as the API shows them to the token holder.
     * @param int<0, max> $broken Number of the newest published releases that cannot be decoded.
     */
    public function __construct(
        private readonly int $pages = 1,
        private readonly int $releasesPerPage = 100,
        private readonly int $drafts = 0,
        private readonly int $broken = 0,
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
            ? ['link' => [\sprintf('<https://api.github.com/repositories/1/releases?page=%d>; rel="next"', $page + 1)]]
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

        return \max(1, (int) ($params['per_page'] ?? 30));
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function allReleases(): array
    {
        $releases = [];
        $total = $this->pages * $this->releasesPerPage;

        for ($i = 1 - $this->drafts; $i <= $total; $i++) {
            $tag = $i < 1 ? \sprintf('draft-%d', 1 - $i) : \sprintf('v1.0.%d', $i);
            $releases[] = [
                'name' => $tag,
                // A number where a string belongs fails the strict constructor of the response object
                'tag_name' => $i >= 1 && $i <= $this->broken ? $i : $tag,
                'published_at' => $i < 1 ? null : '2024-01-01T00:00:00Z',
                'assets' => [[
                    'name' => 'rr-linux-amd64.tar.gz',
                    'browser_download_url' => 'https://github.com/owner/repo/releases/download/' . $tag . '/rr-linux-amd64.tar.gz',
                    'size' => 1024,
                    'content_type' => 'application/gzip',
                    'digest' => 'sha256:' . \hash('sha256', $tag),
                ]],
                'prerelease' => false,
                'draft' => $i < 1,
            ];
        }

        return $releases;
    }
}
