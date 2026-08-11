<?php

declare(strict_types=1);

namespace Internal\DLoad\Tests\Unit\Module\Common;

use Testo\Assert;
use Testo\Data\DataProvider;
use Testo\Test;
use Internal\DLoad\Module\Common\OperatingSystem;

class OperatingSystemTest
{
    public static function provideBuildNames(): iterable
    {
        yield ['roadrunner-2024.1.5-windows-amd64.zip', OperatingSystem::Windows];
        yield ['temporal_cli_0.13.2_windows_amd64.tar.gz', OperatingSystem::Windows];
        yield ['roadrunner-2024.1.5-linux-amd64.deb', OperatingSystem::Linux];
        yield ['roadrunner-2024.1.5-linux-amd64.tar.gz', OperatingSystem::Linux];
        yield ['roadrunner-2024.1.5-unknown-musl-amd64.tar.gz', null];
        yield ['protoc-27.3-win64.zip', OperatingSystem::Windows];
        yield ['protoc-27.3-win32.zip', OperatingSystem::Windows];
        yield ['temporal-test-server_1.33.0_macOS_arm64.tar.gz', OperatingSystem::Darwin];
    }

    #[DataProvider('provideBuildNames')]
    #[Test]
    public function tryFromBuildName(string $name, ?OperatingSystem $expected): void
    {
        Assert::same(OperatingSystem::tryFromBuildName($name), $expected);
    }
}
