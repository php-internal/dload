<?php

declare(strict_types=1);

namespace Internal\DLoad\Tests\Unit\Module\Downloader\Internal\AssetSelection\Stub;

use Internal\DLoad\Module\Common\Architecture;
use Internal\DLoad\Module\Common\OperatingSystem;
use Internal\DLoad\Module\Repository\AssetInterface;
use Internal\DLoad\Module\Version\Version;
use Internal\DLoad\Tests\Unit\Module\Repository\Stub\AssetStub;
use Internal\DLoad\Tests\Unit\Module\Repository\Stub\ReleaseStub;
use Internal\DLoad\Tests\Unit\Module\Repository\Stub\RepositoryStub;

/**
 * Release assets with the OS and architecture read from their names, as repositories do.
 */
final class NamedAssets
{
    /**
     * @param non-empty-string ...$names
     * @return list<AssetInterface>
     */
    public static function create(string ...$names): array
    {
        $release = new ReleaseStub(new RepositoryStub('owner/repo'), 'v1.0.0', Version::fromVersionString('v1.0.0'));

        return \array_values(\array_map(
            static fn(string $name): AssetInterface => new AssetStub(
                $release,
                $name,
                'https://example.com/' . $name,
                OperatingSystem::tryFromBuildName($name),
                Architecture::tryFromBuildName($name),
            ),
            $names,
        ));
    }
}
