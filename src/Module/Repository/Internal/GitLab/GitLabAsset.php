<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\Repository\Internal\GitLab;

use Internal\Destroy\Destroyable;
use Internal\DLoad\Module\Common\Architecture;
use Internal\DLoad\Module\Common\OperatingSystem;
use Internal\DLoad\Module\Repository\Internal\Asset;
use Internal\DLoad\Module\Repository\Internal\GitLab\Api\Response\AssetInfo;
use Internal\DLoad\Module\Repository\Internal\GitLab\Api\RepositoryApi;
use Psr\Http\Client\ClientExceptionInterface;

/**
 * GitLab Asset class representing a downloadable asset from a GitLab release.
 *
 * @internal
 * @psalm-internal Internal\DLoad\Module\Repository\Internal\GitLab
 */
final class GitLabAsset extends Asset implements Destroyable
{
    /**
     * @param non-empty-string $name
     * @param non-empty-string $uri
     */
    private function __construct(
        private readonly RepositoryApi $api,
        GitLabRelease $release,
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
        GitLabRelease $release,
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
        $response = $this->api->downloadArtifact($this->release->getRepository()->getName(), $this->release->getName(), $this->getName());

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
