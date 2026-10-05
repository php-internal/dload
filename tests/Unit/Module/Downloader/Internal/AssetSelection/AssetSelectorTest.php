<?php

declare(strict_types=1);

namespace Internal\DLoad\Tests\Unit\Module\Downloader\Internal\AssetSelection;

use Internal\DLoad\Module\Archive\ArchiveFactory;
use Internal\DLoad\Module\Common\Architecture;
use Internal\DLoad\Module\Common\Libc;
use Internal\DLoad\Module\Common\OperatingSystem;
use Internal\DLoad\Module\Config\Schema\Action\Type;
use Internal\DLoad\Module\Downloader\Internal\AssetSelection\AssetSelector;
use Internal\DLoad\Module\Downloader\Internal\AssetSelection\Rule\ArchitectureRule;
use Internal\DLoad\Module\Downloader\Internal\AssetSelection\Rule\ArchiveRule;
use Internal\DLoad\Module\Downloader\Internal\AssetSelection\Rule\FormatRule;
use Internal\DLoad\Module\Downloader\Internal\AssetSelection\Rule\NamePatternRule;
use Internal\DLoad\Module\Downloader\Internal\AssetSelection\Rule\OperatingSystemRule;
use Internal\DLoad\Module\Repository\AssetInterface;
use Internal\DLoad\Tests\Unit\Module\Downloader\Internal\AssetSelection\Stub\NamedAssets;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Test;

#[Covers(AssetSelector::class)]
#[Covers(NamePatternRule::class)]
#[Covers(FormatRule::class)]
#[Covers(OperatingSystemRule::class)]
#[Covers(ArchitectureRule::class)]
#[Covers(LibcRule::class)]
#[Covers(ArchiveRule::class)]
final class AssetSelectorTest
{
    #[Test]
    public function assetsNotMatchingThePatternAreRemoved(): void
    {
        $names = self::select(['tool-linux-amd64.zip', 'other-linux-amd64.zip'], pattern: '/^tool-/');

        Assert::same($names, ['tool-linux-amd64.zip']);
    }

    #[Test]
    public function aPharActionKeepsOnlyPharAssets(): void
    {
        $names = self::select(['tool.phar', 'tool-linux-amd64.zip', 'tool.phar.asc'], type: Type::Phar, strict: false);

        Assert::same($names, ['tool.phar']);
    }

    #[Test]
    public function anArchiveActionKeepsOnlyArchives(): void
    {
        $names = self::select(['tool-linux-amd64', 'tool-linux-amd64.tar.gz'], type: Type::Archive);

        Assert::same($names, ['tool-linux-amd64.tar.gz']);
    }

    #[Test]
    public function aStrictSelectionRemovesOtherPlatforms(): void
    {
        $names = self::select([
            'tool-darwin-amd64.zip',
            'tool-linux-arm64.zip',
            'tool-linux-amd64.zip',
            'tool-checksums.txt',
        ]);

        Assert::same($names, ['tool-linux-amd64.zip']);
    }

    #[Test]
    public function androidBuildsAreNotSelectedOnLinux(): void
    {
        $names = self::select(['tool-linux-amd64-android.zip', 'tool-linux-amd64.zip']);

        Assert::same($names, ['tool-linux-amd64.zip']);
    }

    #[Test]
    public function androidPrefersAndroidBuildsAndFallsBackToLinuxOnes(): void
    {
        $names = self::select(
            ['tool-darwin-amd64.zip', 'tool-linux-amd64.zip', 'tool-linux-amd64-android.zip'],
            os: OperatingSystem::Android,
        );

        Assert::same($names, ['tool-linux-amd64-android.zip', 'tool-linux-amd64.zip']);
    }

    #[Test]
    public function aGradualSelectionPutsOtherPlatformsAfterTheHostOne(): void
    {
        $names = self::select([
            'tool-darwin-arm64.zip',
            'tool-darwin-amd64.zip',
            'tool-linux-arm64.zip',
            'tool-linux-amd64.zip',
        ], strict: false);

        // The OS outweighs the architecture
        Assert::same($names, [
            'tool-linux-amd64.zip',
            'tool-linux-arm64.zip',
            'tool-darwin-amd64.zip',
            'tool-darwin-arm64.zip',
        ]);
    }

    #[Test]
    public function aGlibcHostPrefersGlibcBuildsAndKeepsMuslOnesAsAFallback(): void
    {
        $names = self::select(['tool-x86_64-unknown-linux-musl.tar.gz', 'tool-x86_64-unknown-linux-gnu.tar.gz']);

        Assert::same($names, ['tool-x86_64-unknown-linux-gnu.tar.gz', 'tool-x86_64-unknown-linux-musl.tar.gz']);
    }

    #[Test]
    public function aMuslHostPrefersMuslBuilds(): void
    {
        $names = self::select(['tool-linux-amd64.tar.gz', 'tool-linux-amd64-musl.tar.gz'], libc: Libc::Musl);

        Assert::same($names, ['tool-linux-amd64-musl.tar.gz', 'tool-linux-amd64.tar.gz']);
    }

    #[Test]
    public function aMuslOnlyReleaseIsSelectedOnAGlibcHost(): void
    {
        $names = self::select(['tool-x86_64-unknown-linux-musl.tar.gz', 'tool-aarch64-unknown-linux-musl.tar.gz']);

        Assert::same($names, ['tool-x86_64-unknown-linux-musl.tar.gz']);
    }

    #[Test]
    public function theLibcIsWeighedAfterThePlatform(): void
    {
        $names = self::select(['tool-linux-arm64.tar.gz', 'tool-linux-amd64-musl.tar.gz'], strict: false);

        Assert::same($names, ['tool-linux-amd64-musl.tar.gz', 'tool-linux-arm64.tar.gz']);
    }

    #[Test]
    public function archivesComeBeforeOtherFiles(): void
    {
        $names = self::select(['tool-linux-amd64', 'tool-linux-amd64.deb', 'tool-linux-amd64.tar.gz']);

        Assert::same($names, ['tool-linux-amd64.tar.gz', 'tool-linux-amd64', 'tool-linux-amd64.deb']);
    }

    /**
     * @param list<non-empty-string> $assets
     * @param non-empty-string $pattern
     * @return list<string>
     */
    private static function select(
        array $assets,
        string $pattern = '/.*/',
        ?Type $type = null,
        bool $strict = true,
        OperatingSystem $os = OperatingSystem::Linux,
        Architecture $arch = Architecture::X86_64,
        Libc $libc = Libc::Gnu,
    ): array {
        $selection = (new AssetSelector($os, $arch, $libc, new ArchiveFactory()))
            ->select(NamedAssets::create(...$assets), $pattern, $type, $strict);

        return \array_map(static fn(AssetInterface $asset): string => $asset->getName(), $selection->assets());
    }
}
