<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\Repository\Internal\GitHub\Api;

use Internal\DLoad\Module\HttpClient\Factory as HttpFactory;
use Internal\DLoad\Module\HttpClient\Method;
use Internal\DLoad\Module\Registry\Record\ReleasePage;
use Internal\DLoad\Module\Repository\Exception\ApiException;
use Internal\DLoad\Module\Repository\Exception\RepositoryException;
use Internal\DLoad\Module\Repository\Internal\GitHub\Api\Response\ReleaseInfo;
use Internal\DLoad\Module\Repository\Internal\GitHub\Api\Response\RepositoryInfo;
use Internal\DLoad\Service\Logger;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\UriInterface;

/**
 * API client for specific GitHub repository operations.
 *
 * Bound to specific owner/repo pair and provides typed methods for GitHub API operations.
 *
 * @internal
 * @psalm-internal Internal\DLoad\Module\Repository\Internal\GitHub
 */
final class RepositoryApi
{
    private const URL_REPOSITORY = 'https://api.github.com/repos/%s';
    private const URL_RELEASES = 'https://api.github.com/repos/%s/releases';

    /**
     * Number of releases to ask for in a single page. GitHub serves 30 by default and allows up to
     * 100, so the maximum keeps the release list within as few requests as the API permits.
     */
    public const RELEASES_PER_PAGE = 100;

    /**
     * @var non-empty-string
     */
    public readonly string $repositoryPath;

    /**
     * @param non-empty-string $owner
     * @param non-empty-string $repo
     */
    public function __construct(
        private readonly Client $client,
        private readonly HttpFactory $httpFactory,
        string $owner,
        string $repo,
        private readonly Logger $logger,
    ) {
        $this->repositoryPath = $owner . '/' . $repo;
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
        $response = $this->request(Method::Get, \sprintf(self::URL_REPOSITORY, $this->repositoryPath));

        /** @var array{
         *     name: string,
         *     full_name: string,
         *     description: string|null,
         *     html_url: string,
         *     private: bool,
         *     created_at: string,
         *     updated_at: string
         * } $data */
        $data = \json_decode($response->getBody()->__toString(), true, 512, JSON_THROW_ON_ERROR);

        return RepositoryInfo::fromApiResponse($data);
    }

    /**
     * Lists releases newest first, page by page, starting from the given page.
     *
     * A page is requested only when the generator advances to it, so a consumer that stops early
     * costs no extra request.
     *
     * @param int<1, max> $page
     * @return \Generator<int, ReleasePage, mixed, void>
     * @throws RepositoryException
     */
    public function releasePages(int $page = 1): \Generator
    {
        $currentPage = $page;

        do {
            $response = $this->releasesRequest($currentPage);

            /** @var list<array{
             *     name: string|null,
             *     tag_name: string,
             *     published_at: string,
             *     assets: array<array-key, array{
             *         name: string,
             *         browser_download_url: string,
             *         size: int,
             *         content_type: string
             *     }>,
             *     prerelease: bool,
             *     draft: bool
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
                    $releases[] = ReleaseInfo::fromApiResponse($releaseData)->toRecord();
                } catch (\Throwable $e) {
                    $failure ??= $e;
                    $this->logger->exception($e, important: false);
                    // Skip invalid releases
                    continue;
                }
            }

            // The whole page is unreadable: the response structure is not what we expect
            if ($releases === [] && $failure !== null) {
                throw new ApiException(
                    \sprintf(
                        'GitHub API returned %d release(s) for repository `%s`, but none of them could be read: %s',
                        \count($data),
                        $this->repositoryPath,
                        $failure->getMessage(),
                    ),
                    $this->repositoryPath,
                    $failure,
                );
            }

            $hasMorePages = $this->hasNextPage($response);

            yield new ReleasePage($releases, !$hasMorePages);

            $currentPage++;
        } while ($hasMorePages);
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
                    'GitHub API returned a malformed response for repository `%s`: %s',
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
                    'GitHub API returned an unexpected response for repository `%s`: '
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
        $uri = $this->httpFactory->uri(
            \sprintf(self::URL_RELEASES, $this->repositoryPath),
            ['page' => $page, 'per_page' => self::RELEASES_PER_PAGE],
        );

        return $this->request(Method::Get, $uri);
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
