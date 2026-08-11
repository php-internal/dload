<?php

declare(strict_types=1);

namespace Internal\DLoad\Tests\Unit\Module\Common;

use Testo\Assert;
use Testo\Data\DataProvider;
use Testo\Test;
use Internal\DLoad\Module\Common\Architecture;

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

    #[DataProvider('provideBuildNames')]
    #[Test]
    public function tryFromBuildName(string $name, ?Architecture $expected): void
    {
        Assert::same(Architecture::tryFromBuildName($name), $expected);
    }
}
