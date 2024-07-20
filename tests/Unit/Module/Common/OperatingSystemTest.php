<?php

declare(strict_types=1);

namespace Internal\DLoad\Tests\Unit\Module\Common;

use Internal\DLoad\Module\Common\OperatingSystem;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class OperatingSystemTest extends TestCase
{
    public static function provideBuildNames(): iterable
    {
        yield ['roadrunner-2024.1.5-windows-amd64.zip', OperatingSystem::Windows];
        yield ['temporal_cli_0.13.2_windows_amd64.tar.gz', OperatingSystem::Windows];
        yield ['roadrunner-2024.1.5-linux-amd64.deb', OperatingSystem::Linux];
        yield ['roadrunner-2024.1.5-linux-amd64.tar.gz', OperatingSystem::Linux];
        yield ['roadrunner-2024.1.5-unknown-musl-amd64.tar.gz', null];
    }

    #[DataProvider('provideBuildNames')]
    public function testTryFromBuildName(string $name, ?OperatingSystem $expected): void
    {
        self::assertSame($expected, OperatingSystem::tryFromBuildName($name));
    }
}
