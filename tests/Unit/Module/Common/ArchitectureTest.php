<?php

declare(strict_types=1);

namespace Internal\DLoad\Tests\Unit\Module\Common;

use Internal\DLoad\Module\Common\Architecture;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ArchitectureTest extends TestCase
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
    }

    #[DataProvider('provideBuildNames')]
    public function testTryFromBuildName(string $name, ?Architecture $expected): void
    {
        self::assertSame($expected, Architecture::tryFromBuildName($name));
    }
}
