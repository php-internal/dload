<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\Repository\Internal\GitLab\Api;

use Internal\DLoad\Module\HttpClient\Factory as HttpFactory;
use Internal\DLoad\Module\HttpClient\Method;
use Internal\DLoad\Module\Repository\Internal\GitLab\Api\Response\ReleaseInfo;
use Internal\DLoad\Module\Repository\Internal\GitLab\Api\Response\RepositoryInfo;
use Internal\DLoad\Module\Repository\Internal\GitLab\Exception\GitLabRateLimitException;
use Internal\DLoad\Module\Repository\Internal\Paginator;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\UriInterface;

/**
 * API client for specific GitLab repository operations.
 *
 * Bound to specific owner/repo pair and provides typed methods for GitLab API operations.
 *
 * @internal
 * @psalm-internal Internal\DLoad\Module\Repository\Internal\GitLab
 */
final class RepositoryApi
{
    private const URL_REPOSITORY = 'https://gitlab.com/api/v4/projects/%s';
    private const URL_RELEASES = 'https://gitlab.com/api/v4/projects/%s/releases';

    /**
     * @var non-empty-string
     */
    public readonly string $repositoryPath;

    /**
     * @param non-empty-string $repo
     */
    public function __construct(
        private readonly Client $client,
        private readonly HttpFactory $httpFactory,
        string $projectPath,
    ) {
        $this->repositoryPath = $projectPath;
    }

    /**
     * @param Method|non-empty-string $method
     * @param array<string, string> $headers
     * @throws GitLabRateLimitException
     * @throws ClientExceptionInterface
     */
    public function request(Method|string $method, string|UriInterface $uri, array $headers = []): ResponseInterface
    {
        return $this->client->request($method, $uri, $headers);
    }

    /**
     * @throws GitLabRateLimitException
     * @throws ClientExceptionInterface
     */
    public function getRepository(): RepositoryInfo
    {
        $response = $this->request(Method::Get, \sprintf(self::URL_REPOSITORY, urlencode($this->repositoryPath)));

        /** @var array{
         *     name: string,
         *     name_with_namespace: string,
         *     description: string|null,
         *     web_url: string,
         *     visibility: bool,
         *     created_at: string,
         *     updated_at: string
         * } $data */
        $data = \json_decode($response->getBody()->__toString(), true, 512, JSON_THROW_ON_ERROR);

        return RepositoryInfo::fromApiResponse($data);
    }

    /**
     * @param int<1, max> $page
     * @return Paginator<ReleaseInfo>
     * @throws GitLabRateLimitException
     * @throws ClientExceptionInterface
     */
    public function getReleases(int $page = 1): Paginator
    {
        $pageLoader = function () use ($page): \Generator {
            $currentPage = $page;

            do {
                try {
                    $response = $this->releasesRequest($currentPage);

                    /** @var array<array-key, array{
                     *     name: string|null,
                     *     tag_name: string,
                     *     released_at: string,
                     *     assets: array{
                     *         links: list<array{
                     *          name: string,
                     *          url: string,
                     *          direct_asset_url: string,
                     *      }>
                     *     },
                     *     upcoming_release: bool
                     * }> $data */
                    $data = \json_decode($response->getBody()->__toString(), true, 512, JSON_THROW_ON_ERROR);

                    // If empty response, no more pages
                    if ($data === []) {
                        return;
                    }

                    $releases = [];
                    foreach ($data as $releaseData) {
                        try {
                            $releases[] = ReleaseInfo::fromApiResponse($releaseData);
                        } catch (\Throwable) {
                            // Skip invalid releases
                            continue;
                        }
                    }

                    yield $releases;

                    // Check if there are more pages
                    $hasMorePages = $this->hasNextPage($response);
                    $currentPage++;
                } catch (ClientExceptionInterface) {
                    return;
                }
            } while ($hasMorePages);
        };

        return Paginator::createFromGenerator($pageLoader(), null);
    }

    /**
     * @param positive-int $page
     * @throws GitLabRateLimitException
     * @throws ClientExceptionInterface
     */
    private function releasesRequest(int $page): ResponseInterface
    {
        return $this->request(
            Method::Get,
            $this->httpFactory->uri(
                \sprintf(self::URL_RELEASES, urlencode($this->repositoryPath)),
                ['page' => $page],
            ),
        );
    }

    private function hasNextPage(ResponseInterface $response): bool
    {
        $headers = $response->getHeaders();
        $link = $headers['link'] ?? [];

        if (!isset($link[0])) {
            return false;
        }

        return \str_contains($link[0], 'rel="next"');
    }
}
