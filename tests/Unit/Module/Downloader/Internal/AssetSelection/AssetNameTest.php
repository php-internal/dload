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
        yield 'universal macOS build' => ['hugo_0.139.0_darwin-universal.tar.gz', ['hugo']];
        yield 'all macOS architectures' => ['tool_1.0.0_macOS_all.tar.gz', ['tool']];
        yield 'universal not next to macOS' => ['universal-ctags-linux-x64.tar.gz', ['universal', 'ctags']];
        yield 'microarchitecture level' => ['tool_1.0.0_linux_amd64_v3.tar.gz', ['tool']];
        yield 'osx and aarch_64' => ['protoc-36.2-osx-aarch_64.zip', ['protoc']];
        yield 'universal binary' => ['protoc-36.2-osx-universal_binary.zip', ['protoc']];
    }

    public static function provideUniversal(): iterable
    {
        yield 'darwin-universal' => ['hugo_0.139.0_darwin-universal.tar.gz', true];
        yield 'universal before macOS' => ['tigerbeetle-universal-macos.zip', true];
        yield 'macOS_all' => ['tool_1.0.0_macOS_all.tar.gz', true];
        yield 'universal2' => ['tool-macos-universal2.zip', true];
        yield 'osx-universal_binary' => ['protoc-36.2-osx-universal_binary.zip', true];
        yield 'osx-universal' => ['tool-osx-universal.tar.gz', true];
        yield 'single architecture' => ['tool-darwin-arm64.tar.gz', false];
        yield 'all without macOS' => ['tool-all-linux-amd64.tar.gz', false];
        yield 'universal without macOS' => ['universal-ctags-linux-x64.tar.gz', false];
        yield 'all inside a word' => ['install-darwin-allinone.zip', false];
    }

    public static function provideLevels(): iterable
    {
        yield 'no level' => ['tool_linux_amd64.tar.gz', 1];
        yield 'v1' => ['tool_linux_amd64_v1.tar.gz', 1];
        yield 'v2' => ['tool_linux_amd64_v2.tar.gz', 2];
        yield 'v3 after x86_64' => ['tool-x86_64_v3-linux.tar.gz', 3];
        yield 'v4 after x86-64' => ['tool-linux-x86-64-v4.tar.gz', 4];
        yield 'version after the arch' => ['tool-linux-amd64-v2.1.0.tar.gz', 1];
        yield 'arm' => ['tool-android-arm64-v8a.zip', 1];
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

    #[DataProvider('provideUniversal')]
    #[Test]
    public function universalMacOsBuildsAreReadFromTheName(string $name, bool $expected): void
    {
        Assert::same(AssetName::fromString($name)->universal, $expected);
    }

    #[DataProvider('provideLevels')]
    #[Test]
    public function theMicroarchitectureLevelIsReadFromTheName(string $name, int $expected): void
    {
        Assert::same(AssetName::fromString($name)->level, $expected);
    }
}
