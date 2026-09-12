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
 * but how many requests it took to get there.
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
     * @param int<1, max> $pages Number of pages the list is split into.
     * @param int<1, max> $releasesPerPage Number of releases on every page.
     */
    public function __construct(
        private readonly int $pages = 1,
        private readonly int $releasesPerPage = 2,
    ) {}

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $query = $request->getUri()->getQuery();
        $this->requests[] = $query;

        $page = self::pageOf($query);

        if ($page > $this->pages) {
            return new ResponseStub(200, [], '[]');
        }

        $headers = $page < $this->pages
            ? ['link' => [\sprintf('<https://gitlab.com/api/v4/projects/1/releases?page=%d>; rel="next"', $page + 1)]]
            : [];

        return new ResponseStub(200, $headers, \json_encode($this->releasesOfPage($page)));
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

    /**
     * @param int<1, max> $page
     * @return list<array<string, mixed>>
     */
    private function releasesOfPage(int $page): array
    {
        $releases = [];
        $offset = ($page - 1) * $this->releasesPerPage;

        for ($i = 1; $i <= $this->releasesPerPage; $i++) {
            $tag = \sprintf('v1.0.%d', $offset + $i);
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
