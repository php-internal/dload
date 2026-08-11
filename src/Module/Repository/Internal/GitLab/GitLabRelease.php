<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\Repository\Internal\GitLab;

use Internal\Destroy\Destroyable;
use Internal\DLoad\Module\Repository\Collection\AssetsCollection;
use Internal\DLoad\Module\Repository\Internal\GitLab\Api\Response\ReleaseInfo;
use Internal\DLoad\Module\Repository\Internal\GitLab\Api\RepositoryApi;
use Internal\DLoad\Module\Repository\Internal\Release;
use Internal\DLoad\Module\Version\Version;

/**
 * GitLab Release class representing a release in a GitLab repository.
 *
 * @internal
 * @psalm-internal Internal\DLoad\Module\Repository\Internal\GitLab
 */
final class GitLabRelease extends Release implements Destroyable
{
    /**
     * @param non-empty-string $name
     */
    private function __construct(
        GitLabRepository $repository,
        string $name,
        Version $version,
    ) {
        parent::__construct($repository, $name, $version);
    }

    public static function fromDTO(
        RepositoryApi $api,
        GitLabRepository $repository,
        ReleaseInfo $dto,
    ): self {
        $version = Version::fromVersionString($dto->tagName);
        $result = new self($repository, $dto->name, $version);

        $result->assets = AssetsCollection::create(static function () use ($api, $result, $dto): \Generator {
            foreach ($dto->assets as $assetDTO) {
                yield GitLabAsset::fromDTO($api, $result, $assetDTO);
            }
        });

        return $result;
    }

    /**
     * `Destroyable` requires this to be idempotent, and the `unset()` below leaves `$assets`
     * uninitialized — a state Psalm does not model for a typed property, hence the suppression.
     *
     * @psalm-suppress RedundantPropertyInitializationCheck
     */
    public function destroy(): void
    {
        isset($this->assets) and $this->assets->map(
            static fn(object $asset) => $asset instanceof Destroyable and $asset->destroy(),
        );

        unset($this->assets, $this->repository);
    }
}
