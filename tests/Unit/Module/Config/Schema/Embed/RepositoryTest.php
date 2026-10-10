<?php

declare(strict_types=1);

namespace Internal\DLoad\Tests\Unit\Module\Config\Schema\Embed;

use Internal\DLoad\Module\Config\Schema\Embed\Repository;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Test;

#[Covers(Repository::class)]
final class RepositoryTest
{
    #[Test]
    public function tagPrefixIsReadFromTheArray(): void
    {
        $repository = Repository::fromArray(['type' => 'github', 'uri' => 'oven-sh/bun', 'tag-prefix' => 'bun-']);

        Assert::same($repository->tagPrefix, 'bun-');
    }

    #[Test]
    public function tagPrefixIsEmptyByDefault(): void
    {
        $repository = Repository::fromArray(['type' => 'github', 'uri' => 'owner/repo']);

        Assert::same($repository->tagPrefix, '');
    }

    #[Test]
    public function serverIsReadFromTheArray(): void
    {
        $repository = Repository::fromArray(['type' => 'github', 'uri' => 'owner/repo', 'server' => 'ghe.example.com']);

        Assert::same($repository->server, 'ghe.example.com');
    }

    #[Test]
    public function serverIsThePublicHostByDefault(): void
    {
        $repository = Repository::fromArray(['type' => 'github', 'uri' => 'owner/repo']);

        Assert::null($repository->server);
    }
}
