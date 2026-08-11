<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\Repository\Internal\GitLab\Api;

use Internal\DLoad\Module\HttpClient\Factory as HttpFactory;
use Internal\DLoad\Module\HttpClient\Method;
use Internal\DLoad\Module\Repository\Exception\ApiException;
use Internal\DLoad\Module\Repository\Exception\RepositoryException;
use Internal\DLoad\Module\Repository\Internal\GitLab\Api\Response\ReleaseInfo;
use Internal\DLoad\Module\Repository\Internal\GitLab\Api\Response\RepositoryInfo;
use Internal\DLoad\Module\Repository\Internal\Paginator;
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
    private const URL_RELEASE_ASSET = 'https://gitlab.com/api/v4/projects/%s/releases/%s/downloads/%s';

    /**
     * @var non-empty-string
     */
    public readonly string $repositoryPath;

    public function __construct(
        private readonly Client $client,
        private readonly HttpFactory $httpFactory,
        string $projectPath,
    ) {
        $this->repositoryPath = $projectPath;
    }

    /**
     * @param non-empty-string $repositoryPath
     * @param non-empty-string $releaseName
     * @param non-empty-string $fileName
     * @throws RepositoryException
     */
    public function downloadArtifact(string $repositoryPath, string $releaseName, string $fileName): ResponseInterface
    {
        $url = \sprintf(self::URL_RELEASE_ASSET, \urlencode($repositoryPath), $releaseName, $fileName);
        return $this->client->downloadArtifact($url);
    }

    /**
     * @param Method|non-empty-string $method
     * @param array<string, string> $headers
     * @throws RepositoryException
     */
    public function request(Method|string $method, string|UriInterface $uri, array $headers = []): ResponseInterface
    {
        return $this->client->request($method, $uri, $headers);
    }

    /**
     * @throws RepositoryException
     */
    public function getRepository(): RepositoryInfo
    {
        $response = $this->request(Method::Get, \sprintf(self::URL_REPOSITORY, \urlencode($this->repositoryPath)));

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
     * @throws RepositoryException
     */
    public function getReleases(int $page = 1): Paginator
    {
        $pageLoader = function () use ($page): \Generator {
            $currentPage = $page;

            do {
                $response = $this->releasesRequest($currentPage);

                /** @var list<array{
                 *     name: non-empty-string|null,
                 *     tag_name: non-empty-string,
                 *     description: null|non-empty-string,
                 *     created_at: non-empty-string,
                 *     released_at: non-empty-string,
                 *     assets: array{
                 *         links: list<array{
                 *             name: non-empty-string,
                 *             url: non-empty-string,
                 *             direct_asset_url?: non-empty-string,
                 *             link_type: non-empty-string,
                 *         }>
                 *     },
                 *     upcoming_release: bool
                 * }> $data */
                $data = $this->decodeReleasesResponse($response);

                // If empty response, no more pages
                if ($data === []) {
                    return;
                }

                $releases = [];
                $failure = null;
                foreach ($data as $releaseData) {
                    try {
                        $releases[] = ReleaseInfo::fromApiResponse($releaseData);
                    } catch (\Throwable $e) {
                        $failure ??= $e;
                        // Skip invalid releases
                        continue;
                    }
                }

                // The whole page is unreadable: the response structure is not what we expect
                if ($releases === [] && $failure !== null) {
                    throw new ApiException(
                        \sprintf(
                            'GitLab API returned %d release(s) for project `%s`, but none of them could be read: %s',
                            \count($data),
                            $this->repositoryPath,
                            $failure->getMessage(),
                        ),
                        $this->repositoryPath,
                        $failure,
                    );
                }

                yield $releases;

                // Check if there are more pages
                $hasMorePages = $this->hasNextPage($response);
                $currentPage++;
            } while ($hasMorePages);
        };

        return Paginator::createFromGenerator($pageLoader(), null);
    }

    /**
     * Decodes a releases list response and validates its shape.
     *
     * @return list<array<string, mixed>>
     * @throws ApiException When the response is not a list of releases.
     */
    private function decodeReleasesResponse(ResponseInterface $response): array
    {
        $body = $response->getBody()->__toString();

        try {
            /** @var mixed $data */
            $data = \json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new ApiException(
                \sprintf(
                    'GitLab API returned a malformed response for project `%s`: %s',
                    $this->repositoryPath,
                    $e->getMessage(),
                ),
                $this->repositoryPath,
                $e,
            );
        }

        if (!\is_array($data) || !\array_is_list($data)) {
            throw new ApiException(
                \sprintf(
                    'GitLab API returned an unexpected response for project `%s`: '
                    . 'a list of releases is expected, got %s.',
                    $this->repositoryPath,
                    \is_array($data) ? 'an object: ' . \substr($body, 0, 200) : \get_debug_type($data),
                ),
                $this->repositoryPath,
            );
        }

        /** @var list<array<string, mixed>> */
        return $data;
    }

    /**
     * @param positive-int $page
     * @throws RepositoryException
     */
    private function releasesRequest(int $page): ResponseInterface
    {
        return $this->request(
            Method::Get,
            $this->httpFactory->uri(
                \sprintf(self::URL_RELEASES, \urlencode($this->repositoryPath)),
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
