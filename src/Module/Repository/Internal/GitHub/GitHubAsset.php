<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\Repository\Internal\GitHub;

use Internal\Destroy\Destroyable;
use Internal\DLoad\Module\Common\Architecture;
use Internal\DLoad\Module\Common\OperatingSystem;
use Internal\DLoad\Module\HttpClient\Method;
use Internal\DLoad\Module\HttpClient\StreamReader;
use Internal\DLoad\Module\Repository\Internal\Asset;
use Internal\DLoad\Module\Repository\Internal\GitHub\Api\Response\AssetInfo;
use Internal\DLoad\Module\Repository\Internal\GitHub\Api\RepositoryApi;
use Internal\DLoad\Module\Repository\Exception\RepositoryException;

/**
 * GitHub Asset class representing a downloadable asset from a GitHub release.
 *
 * @internal
 * @psalm-internal Internal\DLoad\Module\Repository\Internal\GitHub
 */
final class GitHubAsset extends Asset implements Destroyable
{
    /**
     * @param non-empty-string $name
     * @param non-empty-string $uri
     */
    private function __construct(
        private readonly RepositoryApi $api,
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

    public static function fromDTO(
        RepositoryApi $api,
        GitHubRelease $release,
        AssetInfo $dto,
    ): self {
        return new self($api, $release, $dto->name, $dto->downloadUrl);
    }

    /**
     * @param null|\Closure(int $dlNow, int|null $dlSize, array $info): mixed $progress
     *        throwing any exceptions MUST abort the request;
     *        it MUST be called on DNS resolution, on arrival of headers and on completion;
     *        it SHOULD be called on upload/download of data and at least 1/s
     *
     * @return \Generator<int, non-empty-string, mixed, void>
     * @throws RepositoryException
     */
    public function download(?\Closure $progress = null): \Generator
    {
        $response = $this->api->request(Method::Get, $this->getUri());

        yield from StreamReader::chunks($response->getBody(), $progress);
    }

    public function destroy(): void
    {
        unset($this->release);
    }
}
