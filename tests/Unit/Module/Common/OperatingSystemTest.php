<?php

declare(strict_types=1);

namespace Internal\DLoad\Tests\Unit\Module\Common;

use Internal\DLoad\Module\Common\OperatingSystem;
use Testo\Assert;
use Testo\Data\DataProvider;
use Testo\Expect;
use Testo\Test;

class OperatingSystemTest
{
    public static function provideBuildNames(): iterable
    {
        yield ['roadrunner-2024.1.5-windows-amd64.zip', OperatingSystem::Windows];
        yield ['temporal_cli_0.13.2_windows_amd64.tar.gz', OperatingSystem::Windows];
        yield ['roadrunner-2024.1.5-linux-amd64.deb', OperatingSystem::Linux];
        yield ['roadrunner-2024.1.5-linux-amd64.tar.gz', OperatingSystem::Linux];
        yield ['roadrunner-2024.1.5-unknown-musl-amd64.tar.gz', OperatingSystem::Linux];
        yield ['tool-alpine-amd64.tar.gz', OperatingSystem::Linux];
        yield ['protoc-27.3-win64.zip', OperatingSystem::Windows];
        yield ['protoc-27.3-win32.zip', OperatingSystem::Windows];
        yield ['temporal-test-server_1.33.0_macOS_arm64.tar.gz', OperatingSystem::Darwin];
        yield ['bun-linux-x64-android-baseline.zip', OperatingSystem::Android];
        yield ['tool-aarch64-linux-android.tar.gz', OperatingSystem::Android];
    }

    public static function provideHosts(): iterable
    {
        yield 'Linux with the Android runtime' => ['Linux', true, OperatingSystem::Android];
        yield 'Linux without it' => ['Linux', false, OperatingSystem::Linux];
        yield 'not Linux' => ['Darwin', true, OperatingSystem::Darwin];
    }

    #[DataProvider('provideBuildNames')]
    #[Test]
    public function tryFromBuildName(string $name, ?OperatingSystem $expected): void
    {
        Assert::same(OperatingSystem::tryFromBuildName($name), $expected);
    }

    #[DataProvider('provideHosts')]
    #[Test]
    public function androidIsDetectedByItsRuntimeOnLinux(string $osFamily, bool $androidRuntime, OperatingSystem $expected): void
    {
        Assert::same(OperatingSystem::fromHost($osFamily, $androidRuntime), $expected);
    }

    #[Test]
    public function androidCanBeRequestedByName(): void
    {
        Assert::same(OperatingSystem::tryFromString('android'), OperatingSystem::Android);
    }

    #[Test]
    public function anUnknownHostFamilyIsRejected(): void
    {
        Expect::exception(\OutOfRangeException::class);

        OperatingSystem::fromHost('Solaris', false);
    }
}
