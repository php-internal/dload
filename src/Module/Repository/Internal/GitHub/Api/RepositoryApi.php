<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\Repository\Internal\GitHub\Api;

use Internal\DLoad\Module\HttpClient\Factory as HttpFactory;
use Internal\DLoad\Module\HttpClient\Method;
use Internal\DLoad\Module\Repository\Exception\ApiException;
use Internal\DLoad\Module\Repository\Exception\RepositoryException;
use Internal\DLoad\Module\Repository\Internal\GitHub\Api\Response\ReleaseInfo;
use Internal\DLoad\Module\Repository\Internal\GitHub\Api\Response\RepositoryInfo;
use Internal\DLoad\Module\Repository\Internal\Paginator;
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
                        $releases[] = ReleaseInfo::fromApiResponse($releaseData);
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
        return $this->request(
            Method::Get,
            $this->httpFactory->uri(
                \sprintf(self::URL_RELEASES, $this->repositoryPath),
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
