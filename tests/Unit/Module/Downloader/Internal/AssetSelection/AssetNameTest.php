<?php

declare(strict_types=1);

namespace Internal\DLoad\Tests\Unit\Module\Downloader\Internal\AssetSelection;

use Internal\DLoad\Module\Common\Libc;
use Internal\DLoad\Module\Downloader\Internal\AssetSelection\AssetName;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Data\DataProvider;
use Testo\Test;

#[Covers(AssetName::class)]
final class AssetNameTest
{
    public static function provideExtras(): iterable
    {
        yield 'plain build' => ['bun-linux-x64.zip', ['bun']];
        yield 'variant' => ['bun-linux-x64-baseline-profile.zip', ['bun', 'baseline', 'profile']];
        yield 'debug build' => ['tigerbeetle-x86_64-linux-debug.zip', ['tigerbeetle', 'debug']];
        yield 'target triple' => ['mago-1.51.2-x86_64-unknown-linux-gnu.tar.gz', ['mago']];
        yield 'underscores' => ['temporal_cli_1.1.0_linux_amd64.tar.gz', ['temporal', 'cli']];
        yield 'version with v' => ['tool-v1.4.2-darwin-arm64.tar.gz', ['tool']];
        yield 'checksum' => ['tool-linux-amd64.tar.gz.sha256sum', ['tool']];
        yield 'unknown extension' => ['deno-x86_64-unknown-linux-gnu.from-2.9.6.bsdiff', ['deno', 'from', 'bsdiff']];
        yield 'no platform' => ['tool.phar', ['tool']];
    }

    /**
     * @param list<string> $expected
     */
    #[DataProvider('provideExtras')]
    #[Test]
    public function extrasAreTheTokensLeftAfterThePlatformVersionAndExtension(string $name, array $expected): void
    {
        Assert::same(AssetName::fromString($name)->extras, $expected);
    }

    #[Test]
    public function libcIsReadFromTheName(): void
    {
        Assert::same(AssetName::fromString('bun-linux-x64-musl.zip')->libc, Libc::Musl);
        Assert::null(AssetName::fromString('bun-linux-x64.zip')->libc);
    }
}
