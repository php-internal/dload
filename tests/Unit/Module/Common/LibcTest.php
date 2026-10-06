<?php

declare(strict_types=1);

namespace Internal\DLoad\Tests\Unit\Module\Common;

use Internal\DLoad\Module\Common\Input\Build;
use Internal\DLoad\Module\Common\Libc;
use Internal\DLoad\Module\Common\OperatingSystem;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Data\DataProvider;
use Testo\Test;

#[Covers(Libc::class)]
final class LibcTest
{
    public static function provideBuildNames(): iterable
    {
        yield ['mago-1.51.2-x86_64-unknown-linux-musl.tar.gz', Libc::Musl];
        yield ['mago-1.51.2-arm-unknown-linux-musleabihf.tar.gz', Libc::Musl];
        yield ['bun-linux-x64-musl-baseline.zip', Libc::Musl];
        yield ['roadrunner-2024.1.5-unknown-musl-amd64.tar.gz', Libc::Musl];
        yield ['tool-alpine-amd64.tar.gz', Libc::Musl];
        yield ['mago-1.51.2-x86_64-unknown-linux-gnu.tar.gz', Libc::Gnu];
        yield ['mago-1.51.2-arm-unknown-linux-gnueabihf.tar.gz', Libc::Gnu];
        yield ['tool_linux_glibc_amd64.tar.gz', Libc::Gnu];
        yield ['bun-linux-x64.zip', null];
        yield ['muslim-prayer-times-linux-amd64.zip', null];
    }

    public static function provideHosts(): iterable
    {
        yield 'Linux with the musl loader' => ['Linux', true, Libc::Musl];
        yield 'Linux without it' => ['Linux', false, Libc::Gnu];
        yield 'not Linux' => ['Darwin', true, Libc::Gnu];
    }

    #[DataProvider('provideBuildNames')]
    #[Test]
    public function tryFromBuildName(string $name, ?Libc $expected): void
    {
        Assert::same(Libc::tryFromBuildName($name), $expected);
    }

    #[Test]
    public function aMuslOsOptionSelectsMusl(): void
    {
        $build = new Build();
        $build->os = 'alpine';

        Assert::same(Libc::create($build, OperatingSystem::Linux), Libc::Musl);
    }

    #[Test]
    public function androidPrefersMuslBuilds(): void
    {
        Assert::same(Libc::create(new Build(), OperatingSystem::Android), Libc::Musl);
    }

    #[Test]
    public function aHostOtherThanLinuxIsNotProbed(): void
    {
        Assert::same(Libc::create(new Build(), OperatingSystem::Darwin), Libc::Gnu);
    }

    #[DataProvider('provideHosts')]
    #[Test]
    public function muslIsDetectedByItsLoaderOnLinux(string $osFamily, bool $loader, Libc $expected): void
    {
        $root = \sys_get_temp_dir() . '/dload-libc-' . \bin2hex(\random_bytes(6));
        \mkdir($root . '/lib', recursive: true);
        $loader and \touch($root . '/lib/ld-musl-x86_64.so.1');

        try {
            Assert::same(Libc::detect($osFamily, $root), $expected);
        } finally {
            $loader and \unlink($root . '/lib/ld-musl-x86_64.so.1');
            \rmdir($root . '/lib');
            \rmdir($root);
        }
    }
}
