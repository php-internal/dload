<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\Repository\Internal\GitHub;

use Internal\DLoad\Module\Common\Architecture;
use Internal\DLoad\Module\Common\OperatingSystem;
use Internal\DLoad\Module\HttpClient\Method;
use Internal\DLoad\Module\Repository\Internal\Asset;
use Internal\DLoad\Module\Repository\Internal\GitHub\Api\Response\AssetInfo;
use Internal\DLoad\Module\Repository\Internal\GitHub\Api\RepositoryApi;
use Internal\DLoad\Service\Destroyable;
use Psr\Http\Client\ClientExceptionInterface;

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
     * @return \Generator<int, string, mixed, void>
     * @throws ClientExceptionInterface
     */
    public function download(?\Closure $progress = null): \Generator
    {
        $response = $this->api->request(Method::Get, $this->getUri());

        $body = $response->getBody();
        $size = $body->getSize();
        $loaded = 0;

        while (!$body->eof()) {
            $chunk = $body->read(8192);
            $loaded += \strlen($chunk);
            $progress === null or $progress($loaded, $size, []);
            yield $chunk;
        }
    }

    public function destroy(): void
    {
        unset($this->release);
    }
}
