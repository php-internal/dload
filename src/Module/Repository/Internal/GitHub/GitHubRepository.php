<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\Repository\Internal\GitHub;

use Internal\DLoad\Module\Repository\Internal\ReleasesCollection;
use Internal\DLoad\Module\Repository\RepositoryInterface;
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
final class GitHubRepository implements RepositoryInterface, Destroyable
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
     * @param non-empty-string $owner
     * @param non-empty-string $repository
     */
    public function __construct(string $owner, string $repository, HttpClientInterface $client = null)
    {
        $this->name = $owner . '/' . $repository;
        $this->client = $client ?? HttpClient::create();
    }

    /**
     * @param non-empty-string $package Package name in format "owner/repository"
     */
    public static function fromDsn(string $package, HttpClientInterface $client = null): GitHubRepository
    {
        [$owner, $name] = \explode('/', $package);
        return new GitHubRepository($owner, $name, $client);
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

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    public function destroy(): void
    {
        $this->releases === null or \array_walk($this->releases, static fn(object $release) =>
            $release instanceof Destroyable and $release->destroy());

        unset($this->releases, $this->client);
    }

    /**
     * @param string $method
     * @param string $uri
     * @param array $options
     * @return ResponseInterface
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
