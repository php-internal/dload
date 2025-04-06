<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\Repository\Internal\GitHub;

use Internal\DLoad\Module\Repository\Collection\ReleasesCollection;
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
 * @psalm-internal Internal\DLoad\Module\Repository\GitHub
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
     * @throws ExceptionInterface
     */
    public function getReleases(): ReleasesCollection
    {
        return $this->releases ??= ReleasesCollection::from(function () {
            $page = 0;

            // Iterate over all pages
            do {
                $response = $this->releasesRequest(++$page);

                /** @psalm-var GitHubReleaseApiResponse $data */
                foreach ($response->toArray() as $data) {
                    yield GitHubRelease::fromApiResponse($this, $this->client, $data);
                }
            } while ($this->hasNextPage($response));
        });
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
