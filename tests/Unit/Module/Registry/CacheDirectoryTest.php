<?php

declare(strict_types=1);

namespace Internal\DLoad\Tests\Unit\Module\Registry;

use Internal\DLoad\Module\Registry\Internal\CacheDirectory;
use Internal\Path;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Test;

#[Covers(CacheDirectory::class)]
final class CacheDirectoryTest
{
    #[Test]
    public function xdgCacheHomeWins(): void
    {
        $dir = CacheDirectory::resolve(['XDG_CACHE_HOME' => '/var/cache/', 'HOME' => '/home/u', 'LOCALAPPDATA' => 'C:\\x'], windows: true);

        Assert::same((string) $dir, (string) Path::create('/var/cache')->join('dload'));
    }

    #[Test]
    public function localAppDataIsUsedOnWindowsOnly(): void
    {
        $env = ['LOCALAPPDATA' => 'C:\\Users\\u\\AppData\\Local', 'HOME' => '/home/u'];

        Assert::same(
            (string) CacheDirectory::resolve($env, windows: true),
            (string) Path::create('C:\\Users\\u\\AppData\\Local')->join('dload', 'cache'),
        );
        Assert::same(
            (string) CacheDirectory::resolve($env, windows: false),
            (string) Path::create('/home/u')->join('.cache', 'dload'),
        );
    }

    #[Test]
    public function homeIsUsedWhenNothingElseIsSet(): void
    {
        $dir = CacheDirectory::resolve(['HOME' => '/home/u', 'XDG_CACHE_HOME' => '  '], windows: false);

        Assert::same((string) $dir, (string) Path::create('/home/u')->join('.cache', 'dload'));
    }

    #[Test]
    public function userProfileStandsInForHome(): void
    {
        $dir = CacheDirectory::resolve(['USERPROFILE' => 'C:\\Users\\u'], windows: true);

        Assert::same((string) $dir, (string) Path::create('C:\\Users\\u')->join('.cache', 'dload'));
    }

    #[Test]
    public function fallsBackToAPerUserTemporaryDirectory(): void
    {
        $dir = CacheDirectory::resolve(['USER' => 'j doe'], windows: false);

        Assert::same((string) $dir, (string) Path::create(\sys_get_temp_dir())->join('dload-cache-j_doe'));

        // Without a user name in the environment the process owner keeps the directories apart
        $anonymous = (string) CacheDirectory::resolve([], windows: false);
        $prefix = (string) Path::create(\sys_get_temp_dir())->join('dload-cache-');
        Assert::true(\str_starts_with($anonymous, $prefix));
        Assert::true(\strlen($anonymous) > \strlen($prefix));
    }
}
