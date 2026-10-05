<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\Repository\Internal\GitLab;

use Internal\Destroy\Destroyable;
use Internal\DLoad\Module\Registry\Record\ReleaseRecord;
use Internal\DLoad\Module\Repository\Collection\AssetsCollection;
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
     * @param non-empty-string $tag
     */
    private function __construct(
        GitLabRepository $repository,
        string $name,
        string $tag,
        Version $version,
    ) {
        parent::__construct($repository, $name, $tag, $version);
    }

    /**
     * @param string $tagPrefix Prefix the tag starts with; it is not part of the version.
     * @throws \InvalidArgumentException When the release tag is not a version.
     */
    public static function fromRecord(
        RepositoryApi $api,
        GitLabRepository $repository,
        ReleaseRecord $record,
        string $tagPrefix = '',
    ): self {
        $version = \substr($record->tag, \strlen($tagPrefix));
        $version === '' and throw new \InvalidArgumentException("Release tag `{$record->tag}` has no version.");
        $version = Version::fromVersionString($version);
        $result = new self($repository, $record->name, $record->tag, $version);

        $result->assets = AssetsCollection::create(static function () use ($api, $result, $record): \Generator {
            foreach ($record->assets as $asset) {
                yield GitLabAsset::fromRecord($api, $result, $asset);
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
