<?php

declare(strict_types=1);

namespace Internal\DLoad\Tests\Unit\Module\Registry;

use Internal\DLoad\Module\Registry\Internal\CacheDirectory;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Test;

#[Covers(CacheDirectory::class)]
final class CacheDirectoryTest
{
    #[Test]
    public function xdgCacheHomeWins(): void
    {
        $dir = CacheDirectory::resolve(['XDG_CACHE_HOME' => '/var/cache/', 'HOME' => '/home/u', 'LOCALAPPDATA' => 'C:\\x']);

        Assert::same($dir, '/var/cache' . \DIRECTORY_SEPARATOR . 'dload');
    }

    #[Test]
    public function homeIsUsedWhenNothingElseIsSet(): void
    {
        $dir = CacheDirectory::resolve(['HOME' => '/home/u', 'XDG_CACHE_HOME' => '  ']);

        Assert::same($dir, '/home/u' . \DIRECTORY_SEPARATOR . '.cache' . \DIRECTORY_SEPARATOR . 'dload');
    }

    #[Test]
    public function fallsBackToTheTemporaryDirectory(): void
    {
        Assert::same(CacheDirectory::resolve([]), \sys_get_temp_dir() . \DIRECTORY_SEPARATOR . 'dload-cache');
    }
}
