<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\Repository\Internal\GitHub;

use Internal\DLoad\Module\Repository\Collection\AssetsCollection;
use Internal\DLoad\Module\Repository\Internal\GitHub\Api\Response\ReleaseInfo;
use Internal\DLoad\Module\Repository\Internal\GitHub\Api\RepositoryApi;
use Internal\DLoad\Module\Repository\Internal\Release;
use Internal\DLoad\Module\Version\Version;
use Internal\DLoad\Service\Destroyable;

/**
 * GitHub Release class representing a release in a GitHub repository.
 *
 * @internal
 * @psalm-internal Internal\DLoad\Module\Repository\Internal\GitHub
 */
final class GitHubRelease extends Release implements Destroyable
{
    /**
     * @param non-empty-string $name
     */
    private function __construct(
        GitHubRepository $repository,
        string $name,
        Version $version,
    ) {
        parent::__construct($repository, $name, $version);
    }

    public static function fromDTO(
        RepositoryApi $api,
        GitHubRepository $repository,
        ReleaseInfo $dto,
    ): self {
        $version = Version::fromVersionString($dto->tagName);
        $result = new self($repository, $dto->name, $version);

        $result->assets = AssetsCollection::create(static function () use ($api, $result, $dto): \Generator {
            foreach ($dto->assets as $assetDTO) {
                yield GitHubAsset::fromDTO($api, $result, $assetDTO);
            }
        });

        return $result;
    }

    public function destroy(): void
    {
        $this->assets === null or $this->assets->map(
            static fn(object $asset) => $asset instanceof Destroyable and $asset->destroy(),
        );

        unset($this->assets, $this->repository);
    }
}
