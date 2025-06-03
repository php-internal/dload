<?php

declare(strict_types=1);

namespace Internal\DLoad\Tests\Unit\Module\Repository\Internal\GitHub;

use Internal\DLoad\Module\Repository\Internal\GitHub\GitHubRepository;
use Internal\DLoad\Module\Repository\ReleaseInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class GitHubRepositoryLazyLoadingTest extends TestCase
{
    public function testLazyLoading(): void
    {
        $requestLog = [];

        // Create a mock HTTP client that tracks page requests
        $mockClient = new MockHttpClient(static function (string $method, string $url) use (&$requestLog): MockResponse {
            // Extract page number from URL query
            \parse_str(\parse_url($url, PHP_URL_QUERY) ?? '', $query);
            $page = (int) ($query['page'] ?? 1);

            // Log this request
            $requestLog[] = "Requested page $page";

            // Create response data based on page
            $releases = [];
            if ($page <= 2) {
                // Each page has 2 releases
                for ($i = 1; $i <= 2; $i++) {
                    $releaseNumber = ($page - 1) * 2 + $i;
                    $releases[] = [
                        'name' => "Release $releaseNumber",
                        'tag_name' => "v1.$releaseNumber.0",
                        'assets' => [],
                    ];
                }
            }

            // Add Link header for pagination
            $headers = [];
            if ($page < 3) {
                $nextPage = $page + 1;
                $headers['Link'] = ["<https://api.github.com/repos/test/repo/releases?page=$nextPage>; rel=\"next\""];
            }

            return new MockResponse(\json_encode($releases), [
                'response_headers' => $headers,
            ]);
        });

        // Create repository
        $repository = new GitHubRepository('test', 'repo', $mockClient);

        // At this point, no requests should have been made
        $this->assertEmpty($requestLog);

        // Get releases collection
        $releases = $repository->getReleases();

        // Still no requests should have been made
        $this->assertEmpty($requestLog);

        // Get first release - this should load the first page
        $firstRelease = $releases->first();
        $this->assertCount(1, $requestLog);
        $this->assertEquals("Requested page 1", $requestLog[0]);
        $this->assertEquals("v1.1.0", $firstRelease?->getVersion());

        // Filter to releases with version greater than v1.2.0
        // This should NOT load additional pages yet
        $laterReleases = $releases->filter(static fn(ReleaseInterface $release) => \version_compare($release->getVersion()->string, "v1.2.0", ">"));

        // No additional pages should have been loaded after filter call
        $this->assertCount(1, $requestLog);

        // Add another filter - this should still not load additional pages
        $laterReleases = $laterReleases->filter(static fn(ReleaseInterface $release) => \str_contains($release->getVersion()->string, "v1."));

        // Still no additional pages loaded
        $this->assertCount(1, $requestLog);

        // Get the first matching item - this should load pages until a match is found
        $firstLaterRelease = $laterReleases->first();

        // Now page 2 should be loaded because v1.1.0 and v1.2.0 don't match our filter
        $this->assertCount(2, $requestLog);
        $this->assertEquals("Requested page 2", $requestLog[1]);
        $this->assertEquals("v1.3.0", $firstLaterRelease?->getVersion());

        // Converting to array to force loading all pages
        $allLaterReleases = $laterReleases->toArray();

        // Now all pages should have been requested
        $this->assertCount(3, $requestLog);
        $this->assertEquals("Requested page 3", $requestLog[2]);

        // Check that we have the correct releases
        $this->assertCount(2, $allLaterReleases);
        $this->assertEquals("v1.3.0", $allLaterReleases[0]->getVersion());
        $this->assertEquals("v1.4.0", $allLaterReleases[1]->getVersion());
    }

    public function testMultipleFiltersWithoutIteration(): void
    {
        $requestLog = [];

        // Create a mock HTTP client that tracks page requests
        $mockClient = new MockHttpClient(static function (string $method, string $url) use (&$requestLog): MockResponse {
            // Extract page number from URL query
            \parse_str(\parse_url($url, PHP_URL_QUERY) ?? '', $query);
            $page = (int) ($query['page'] ?? 1);

            // Log this request
            $requestLog[] = "Requested page $page";

            return new MockResponse(\json_encode([
                [
                    'name' => "Release $page",
                    'tag_name' => "v1.$page.0",
                    'assets' => [],
                ],
            ]));
        });

        // Create repository
        $repository = new GitHubRepository('test', 'repo', $mockClient);
        $releases = $repository->getReleases();

        // Apply multiple filters without iterating
        $filtered = $releases
            ->filter(static fn($release) => \str_contains($release->getVersion(), "v1"))
            ->filter(static fn($release) => \version_compare($release->getVersion(), "v1.0.0", ">"))
            ->filter(static fn($release) => $release->getName() !== "Ignored");

        // No requests should have been made yet
        $this->assertEmpty($requestLog);
    }

    public function testEmptyRepository(): void
    {
        // Create a mock HTTP client that returns empty responses
        $mockClient = new MockHttpClient([
            new MockResponse('[]'), // Empty first page
        ]);

        // Create repository
        $repository = new GitHubRepository('test', 'empty-repo', $mockClient);

        // Get releases collection
        $releases = $repository->getReleases();

        // Check that the collection is empty
        $this->assertTrue($releases->empty());
        $this->assertNull($releases->first());
        $this->assertCount(0, \iterator_to_array($releases));
    }

    public function testChainingMultipleFilters(): void
    {
        // Create a mock HTTP client with predefined responses
        $mockClient = new MockHttpClient([
            // Page 1
            new MockResponse(\json_encode([
                [
                    'name' => 'Release 1',
                    'tag_name' => 'v1.0.0',
                    'assets' => [
                        ['name' => 'asset1.zip', 'browser_download_url' => 'https://example.com/asset1.zip'],
                    ],
                ],
                [
                    'name' => 'Release 2',
                    'tag_name' => 'v2.0.0',
                    'assets' => [],
                ],
            ]), [
                'response_headers' => [
                    'Link' => ['<https://api.github.com/repos/test/repo/releases?page=2>; rel="next"'],
                ],
            ]),
            // Page 2
            new MockResponse(\json_encode([
                [
                    'name' => 'Release 3',
                    'tag_name' => 'v3.0.0',
                    'assets' => [
                        ['name' => 'asset3.zip', 'browser_download_url' => 'https://example.com/asset3.zip'],
                    ],
                ],
                [
                    'name' => 'Release 4',
                    'tag_name' => 'v4.0.0',
                    'assets' => [
                        ['name' => 'asset4.zip', 'browser_download_url' => 'https://example.com/asset4.zip'],
                    ],
                ],
            ])),
        ]);

        // Create repository and get releases
        $repository = new GitHubRepository('test', 'repo', $mockClient);
        $releases = $repository->getReleases();

        // Create a filtered collection with multiple filters
        $filteredReleases = $releases
            ->filter(static fn(ReleaseInterface $release) => \version_compare($release->getVersion()->string, "v2.0.0", ">=")) // v2.0.0 and above
            ->filter(static fn(ReleaseInterface $release) => !$release->getAssets()->empty()); // Only with assets

        // Convert to array to execute the filters
        $result = $filteredReleases->toArray();

        // Should have 2 releases: v3.0.0 and v4.0.0 (both have assets and >= v2.0.0)
        $this->assertCount(2, $result);
        $this->assertEquals("v3.0.0", $result[0]->getVersion());
        $this->assertEquals("v4.0.0", $result[1]->getVersion());
    }
}
