<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\Repository\Internal\GitHub;

use Internal\Destroy\Destroyable;
use Internal\DLoad\Module\Registry\Record\ReleaseRecord;
use Internal\DLoad\Module\Repository\Collection\AssetsCollection;
use Internal\DLoad\Module\Repository\Internal\GitHub\Api\RepositoryApi;
use Internal\DLoad\Module\Repository\Internal\Release;
use Internal\DLoad\Module\Version\Version;

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

    /**
     * @throws \InvalidArgumentException When the release tag is not a version.
     */
    public static function fromRecord(
        RepositoryApi $api,
        GitHubRepository $repository,
        ReleaseRecord $record,
    ): self {
        $version = Version::fromVersionString($record->tag);
        $result = new self($repository, $record->name, $version);

        $result->assets = AssetsCollection::create(static function () use ($api, $result, $record): \Generator {
            foreach ($record->assets as $asset) {
                yield GitHubAsset::fromRecord($api, $result, $asset);
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
