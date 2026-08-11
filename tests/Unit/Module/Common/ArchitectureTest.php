<?php

declare(strict_types=1);

namespace Internal\DLoad\Tests\Unit\Module\Common;

use Internal\DLoad\Module\Common\Architecture;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ArchitectureTest
{
    public static function provideBuildNames(): iterable
    {
        yield ['roadrunner-2024.1.5-windows-amd64.zip', Architecture::X86_64];
        yield ['temporal_cli_0.13.2_windows_amd64.tar.gz', Architecture::X86_64];
        yield ['temporal_cli_0.13.2_windows_aaamd64.tar.gz', null];
        yield ['temporal_cli_0.13.2_windows.amd644.tar.gz', null];
        yield ['roadrunner-2024.1.5-windows.zip', null];
        yield ['roadrunner-2024.1.5-linux-amd64.deb', Architecture::X86_64];
        yield ['protoc-27.3-win64.zip', Architecture::X86_64];
        yield ['temporal-test-server_1.33.0_macOS_arm64.tar.gz', Architecture::ARM_64];
    }

    #[\Testo\Data\DataProvider('provideBuildNames')]
    #[\Testo\Test]
    public function testTryFromBuildName(string $name, ?Architecture $expected): void
    {
        \Testo\Assert::same(Architecture::tryFromBuildName($name), $expected);
    }
}
