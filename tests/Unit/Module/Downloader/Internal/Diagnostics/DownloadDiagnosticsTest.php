<?php

declare(strict_types=1);

namespace Internal\DLoad\Tests\Unit\Module\Downloader\Internal\Diagnostics;

use Internal\DLoad\Module\Common\Architecture;
use Internal\DLoad\Module\Common\OperatingSystem;
use Internal\DLoad\Module\Common\Stability;
use Internal\DLoad\Module\Config\Schema\Action\Download as DownloadConfig;
use Internal\DLoad\Module\Config\Schema\Action\Type;
use Internal\DLoad\Module\Config\Schema\Embed\Software;
use Internal\DLoad\Module\Downloader\Internal\Diagnostics\DownloadDiagnostics;
use Internal\DLoad\Module\Repository\Exception\ApiException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[\Testo\Codecov\Covers(DownloadDiagnostics::class)]
#[\Testo\Codecov\Covers(\Internal\DLoad\Module\Downloader\Internal\Diagnostics\RepositoryAttempt::class)]
#[\Testo\Codecov\Covers(\Internal\DLoad\Module\Downloader\Internal\Diagnostics\ReleaseAttempt::class)]
final class DownloadDiagnosticsTest
{
    #[\Testo\Test]
    public function testReportDescribesTheRequestedConditions(): void
    {
        // Arrange
        $diagnostics = self::diagnostics(version: '^2.0', type: Type::Binary);

        // Act
        $report = $diagnostics->render();

        // Assert
        self::assertStringContainsString(
            'Requested: version `^2.0`, OS `linux`, architecture `amd64`, '
            . 'minimum stability `stable`, asset type `binary`.',
            $report,
        );
    }

    #[\Testo\Test]
    public function testReportMentionsMissingRepositoryConfiguration(): void
    {
        // Arrange
        $diagnostics = self::diagnostics();

        // Act
        $report = $diagnostics->render();

        // Assert
        self::assertStringContainsString('No repositories are configured for `app`', $report);
    }

    #[\Testo\Test]
    public function testReportListsAvailableReleasesWhenNothingMatches(): void
    {
        // Arrange
        $diagnostics = self::diagnostics(version: '^5.0');
        $repository = $diagnostics->addRepository('github', 'owner/repo', '/^app-.*/');
        $repository->matchedReleases = 0;
        $repository->registerFetchedReleases(['v1.2.0', 'v1.1.0']);

        // Act
        $report = $diagnostics->render();

        // Assert
        self::assertStringContainsString('Tried 1 repository(ies):', $report);
        self::assertStringContainsString('1) github `owner/repo`', $report);
        self::assertStringContainsString('0 release(s) match the requested version and stability.', $report);
        self::assertStringContainsString('Releases available in the repository: v1.2.0, v1.1.0', $report);
    }

    #[\Testo\Test]
    public function testReportListsCheckedReleasesWithTheirAssets(): void
    {
        // Arrange
        $diagnostics = self::diagnostics();
        $repository = $diagnostics->addRepository('github', 'owner/repo', '/^app-.*/');
        $repository->matchedReleases = 2;

        $release = $repository->addRelease('v1.2.0');
        $release->registerAssets(['app-1.2.0-darwin-arm64.tar.gz', 'app-1.2.0-windows-amd64.zip']);
        $release->reason = 'no asset matches OS `linux`, architecture `amd64`, name pattern `/^app-.*/`';

        $failed = $repository->addRelease('v1.1.0');
        $failed->registerAssets(['app-1.1.0-linux-amd64.tar.gz']);
        $failed->addFailure('app-1.1.0-linux-amd64.tar.gz', new \RuntimeException('Broken archive'));

        // Act
        $report = $diagnostics->render();

        // Assert
        self::assertStringContainsString('2 release(s) match the requested version and stability.', $report);
        self::assertStringContainsString('Checked releases:', $report);
        self::assertStringContainsString('- v1.2.0: 2 asset(s), no asset matches OS `linux`', $report);
        self::assertStringContainsString('Assets: app-1.2.0-darwin-arm64.tar.gz, app-1.2.0-windows-amd64.zip', $report);
        self::assertStringContainsString(
            'Failed asset `app-1.1.0-linux-amd64.tar.gz`: Broken archive',
            $report,
        );
    }

    #[\Testo\Test]
    public function testReportContainsRepositoryLevelError(): void
    {
        // Arrange
        $diagnostics = self::diagnostics();
        $repository = $diagnostics->addRepository('github', 'owner/repo', '/^app-.*/');
        $repository->error = new ApiException(
            "GitHub API rate limit exceeded.\nSet the GITHUB_TOKEN environment variable.",
            'owner/repo',
        );

        $fallback = $diagnostics->addRepository('gitlab', 'group/app', '/^app-.*/');
        $fallback->matchedReleases = 0;

        // Act
        $report = $diagnostics->render();

        // Assert
        self::assertStringContainsString('Tried 2 repository(ies):', $report);
        self::assertStringContainsString('GitHub API rate limit exceeded.', $report);
        self::assertStringContainsString('Set the GITHUB_TOKEN environment variable.', $report);
        self::assertStringContainsString('2) gitlab `group/app`', $report);
    }

    #[\Testo\Test]
    public function testReleaseWithoutFetchedAssetListIsNotReportedAsEmpty(): void
    {
        // Arrange
        $diagnostics = self::diagnostics();
        $repository = $diagnostics->addRepository('github', 'owner/repo', '/^app-.*/');
        $repository->matchedReleases = 1;

        // An error interrupted the attempt before the asset list was fetched
        $repository->addRelease('v1.2.0')->reason = 'GitHub API is unavailable: HTTP 502 Bad Gateway';

        // Act
        $report = $diagnostics->render();

        // Assert
        self::assertStringContainsString('- v1.2.0: asset list not loaded, GitHub API is unavailable', $report);
        self::assertStringNotContainsString('0 asset(s)', $report);
    }

    #[\Testo\Test]
    public function testReportLimitsTheNumberOfDescribedReleases(): void
    {
        // Arrange
        $diagnostics = self::diagnostics();
        $repository = $diagnostics->addRepository('github', 'owner/repo', '/^app-.*/');
        $repository->matchedReleases = 8;

        for ($i = 8; $i > 0; --$i) {
            $repository->addRelease("v1.0.{$i}")->registerAssets(["app-1.0.{$i}-linux-amd64.tar.gz"]);
        }

        // Act
        $report = $diagnostics->render();

        // Assert
        self::assertStringContainsString('- v1.0.8:', $report);
        self::assertStringContainsString('- v1.0.6:', $report);
        self::assertStringNotContainsString('- v1.0.5:', $report);
        self::assertStringContainsString('and 5 more release(s)', $report);
    }

    /**
     * @param non-empty-string|null $version
     */
    private static function diagnostics(?string $version = null, ?Type $type = null): DownloadDiagnostics
    {
        $software = Software::fromArray(['name' => 'App', 'alias' => 'app']);

        $config = DownloadConfig::fromSoftwareId('app');
        $config->version = $version;
        $config->type = $type;

        return new DownloadDiagnostics(
            software: $software,
            actionConfig: $config,
            operatingSystem: OperatingSystem::Linux,
            architecture: Architecture::X86_64,
            stability: Stability::Stable,
        );
    }
}
