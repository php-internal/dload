<?php

declare(strict_types=1);

namespace Internal\DLoad\Tests\Unit\Module\Registry;

use Internal\DLoad\Module\Registry\Internal\PassThroughRegistry;
use Internal\DLoad\Module\Registry\Record\ReleaseRecord;
use Internal\DLoad\Module\Registry\RepositoryId;
use Internal\DLoad\Tests\Unit\Module\Registry\Stub\ArrayReleaseSource;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Test;

#[Covers(PassThroughRegistry::class)]
final class PassThroughRegistryTest
{
    #[Test]
    public function everyListingGoesToTheSource(): void
    {
        $registry = new PassThroughRegistry();
        $id = new RepositoryId('github', 'owner/repo');
        $source = ArrayReleaseSource::ofTags(['v3', 'v2', 'v1']);

        $registry->attach($id, 'rr');
        $first = \iterator_to_array($registry->releases($id, $source), false);
        $registry->forget($id, 'v3');
        $second = \iterator_to_array($registry->releases($id, $source), false);

        Assert::same($source->served, [0, 2, 0, 2]);
        Assert::same($first, $second);
        Assert::same(
            \array_map(static fn(ReleaseRecord $release): string => $release->tag, \array_merge(...$first)),
            ['v3', 'v2', 'v1'],
        );
    }
}
