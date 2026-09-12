<?php

declare(strict_types=1);

namespace Internal\DLoad\Tests\Unit\Module\Repository\Internal\GitHub\Stub;

use Internal\DLoad\Tests\Unit\Module\Repository\Stub\ResponseStub;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

final class PagedClientStub implements ClientInterface
{
    /**
     * @var list<string>
     */
    public array $requests = [];

    /**
     * @param int<1, max> $pages
     * @param int<1, max> $releasesPerPage
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
            ? ['link' => [\sprintf('<https://api.github.com/repositories/1/releases?page=%d>; rel="next"', $page + 1)]]
            : [];

        return new ResponseStub(200, $headers, \json_encode($this->releasesOfPage($page)));
    }

    /**
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
                'published_at' => '2024-01-01T00:00:00Z',
                'assets' => [],
                'prerelease' => false,
                'draft' => false,
            ];
        }

        return $releases;
    }
}
