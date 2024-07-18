<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\Repository\Internal\GitHub;

use Internal\DLoad\Module\Common\Architecture;
use Internal\DLoad\Module\Common\OperatingSystem;
use Internal\DLoad\Module\Repository\Internal\Asset;
use Internal\DLoad\Service\Destroyable;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @psalm-type GitHubAssetApiResponse = array{
 *      name: non-empty-string,
 *      browser_download_url: non-empty-string
 * }
 *
 * @internal
 * @psalm-internal Internal\DLoad\Module\Repository\GitHub
 */
final class GitHubAsset extends Asset implements Destroyable
{
    /**
     * @param non-empty-string $name
     * @param non-empty-string $uri
     */
    public function __construct(
        private HttpClientInterface $client,
        GitHubRelease $release,
        string $name,
        string $uri,
    ) {
        parent::__construct(
            release: $release,
            name: $name,
            uri: $uri,
            os: OperatingSystem::tryFromBuildName($name),
            arch: Architecture::tryFromBuildName($name),
        );
    }

    /**
     * @param GitHubAssetApiResponse $data
     */
    public static function fromApiResponse(HttpClientInterface $client, GitHubRelease $release, array $data): self
    {
        // Validate name
        \is_string($data['name'] ?? null) or throw new \InvalidArgumentException(
            'Passed array must contain "name" value of type string.',
        );

        // Validate uri
        \is_string($data['browser_download_url'] ?? null) or throw new \InvalidArgumentException(
            'Passed array must contain "browser_download_url" key of type string.',
        );

        return new self($client, $release, $data['name'], $data['browser_download_url']);
    }

    /**
     * @param null|\Closure(int $dlNow, int $dlSize, array $info): mixed $progress
     *        throwing any exceptions MUST abort the request;
     *        it MUST be called on DNS resolution, on arrival of headers and on completion;
     *        it SHOULD be called on upload/download of data and at least 1/s
     *
     * @throws ExceptionInterface
     */
    public function download(\Closure $progress = null): \Traversable
    {
        $response = $this->client->request('GET', $this->getUri(), [
            'on_progress' => $progress,
        ]);

        foreach ($this->client->stream($response) as $chunk) {
            yield $chunk->getContent();
        }
    }

    public function destroy(): void
    {
        unset($this->release, $this->client);
    }
}
