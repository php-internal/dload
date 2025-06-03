<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\Repository\Internal\GitHub;

use Internal\DLoad\Module\Repository\Collection\ReleasesCollection;
use Internal\DLoad\Module\Repository\Internal\Paginator;
use Internal\DLoad\Module\Repository\Repository;
use Internal\DLoad\Service\Destroyable;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * @psalm-import-type GitHubReleaseApiResponse from GitHubRelease
 * @internal
 * @psalm-internal Internal\DLoad\Module\Repository\Internal\GitHub
 */
final class GitHubRepository implements Repository, Destroyable
{
    private const URL_RELEASES = 'https://api.github.com/repos/%s/releases';

    private HttpClientInterface $client;
    private ?ReleasesCollection $releases = null;

    /**
     * Package name in format "owner/repository"
     *
     * @var non-empty-string
     */
    private string $name;

    /**
     * @var array<non-empty-string, non-empty-string>
     */
    private array $headers = [
        'accept' => 'application/vnd.github.v3+json',
    ];

    /**
     * @param non-empty-string $org
     * @param non-empty-string $repo
     */
    public function __construct(string $org, string $repo, ?HttpClientInterface $client = null)
    {
        $this->name = $org . '/' . $repo;
        $this->client = $client ?? HttpClient::create();
    }

    /**
     * Returns a lazily loaded collection of repository releases.
     * Pages are loaded only when needed during iteration or filtering.
     *
     * @throws ExceptionInterface
     */
    public function getReleases(): ReleasesCollection
    {
        if ($this->releases !== null) {
            return $this->releases;
        }

        // Create a generator function for lazy loading release pages
        $pageLoader = function (): \Generator {
            $page = 0;

            do {
                try {
                    // to avoid first eager loading because of generator
                    yield [];

                    $response = $this->releasesRequest(++$page);

                    /** @psalm-var array<array-key, array{name: string|null, tag_name: string|null, assets: array}> $data */
                    $data = $response->toArray();

                    // If empty response, no more pages
                    if ($data === []) {
                        return;
                    }

                    $toYield = [];
                    foreach ($data as $record) {
                        try {
                            $toYield[] = GitHubRelease::fromApiResponse($this, $this->client, $record);
                        } catch (\Throwable) {
                            // Skip invalid releases
                            continue;
                        }
                    }
                    yield $toYield;

                    // Check if there are more pages
                    $hasMorePages = $this->hasNextPage($response);
                } catch (ExceptionInterface) {
                    return;
                }
            } while ($hasMorePages);
        };

        // Create paginator
        $paginator = Paginator::createFromGenerator($pageLoader(), null);

        // Create a collection with the paginator
        $this->releases = ReleasesCollection::create($paginator);

        return $this->releases;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function destroy(): void
    {
        $this->releases === null or $this->releases->map(
            static fn(object $release) => $release instanceof Destroyable and $release->destroy(),
        );

        unset($this->releases, $this->client);
    }

    /**
     * @throws TransportExceptionInterface
     * @see HttpClientInterface::request()
     */
    protected function request(string $method, string $uri, array $options = []): ResponseInterface
    {
        // Merge headers with defaults
        $options['headers'] = \array_merge($this->headers, (array) ($options['headers'] ?? []));

        return $this->client->request($method, $uri, $options);
    }

    /**
     * @param positive-int $page
     * @throws TransportExceptionInterface
     */
    private function releasesRequest(int $page): ResponseInterface
    {
        return $this->request('GET', $this->uri(self::URL_RELEASES), [
            'query' => [
                'page' => $page,
            ],
        ]);
    }

    /**
     * @param non-empty-string $pattern
     * @return non-empty-string
     */
    private function uri(string $pattern): string
    {
        return \sprintf($pattern, $this->getName());
    }

    /**
     * @throws ExceptionInterface
     */
    private function hasNextPage(ResponseInterface $response): bool
    {
        $headers = $response->getHeaders();
        $link = $headers['link'] ?? [];

        if (! isset($link[0])) {
            return false;
        }

        return \str_contains($link[0], 'rel="next"');
    }
}
