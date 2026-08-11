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
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Test;

#[Covers(DownloadDiagnostics::class)]
#[Covers(\Internal\DLoad\Module\Downloader\Internal\Diagnostics\RepositoryAttempt::class)]
#[Covers(\Internal\DLoad\Module\Downloader\Internal\Diagnostics\ReleaseAttempt::class)]
final class DownloadDiagnosticsTest
{
    #[Test]
    public function reportDescribesTheRequestedConditions(): void
    {
        $diagnostics = self::diagnostics(version: '^2.0', type: Type::Binary);

        $report = $diagnostics->render();

        Assert::string($report)->contains(
            'Requested: version `^2.0`, OS `linux`, architecture `amd64`, '
            . 'minimum stability `stable`, asset type `binary`.',
        );
    }

    #[Test]
    public function reportMentionsMissingRepositoryConfiguration(): void
    {
        $diagnostics = self::diagnostics();

        $report = $diagnostics->render();

        Assert::string($report)->contains('No repositories are configured for `app`');
    }

    #[Test]
    public function reportListsAvailableReleasesWhenNothingMatches(): void
    {
        $diagnostics = self::diagnostics(version: '^5.0');
        $repository = $diagnostics->addRepository('github', 'owner/repo', '/^app-.*/');
        $repository->matchedReleases = 0;
        $repository->registerFetchedReleases(['v1.2.0', 'v1.1.0']);

        $report = $diagnostics->render();

        Assert::string($report)->contains('Tried 1 repository(ies):');
        Assert::string($report)->contains('1) github `owner/repo`');
        Assert::string($report)->contains('0 release(s) match the requested version and stability.');
        Assert::string($report)->contains('Releases available in the repository: v1.2.0, v1.1.0');
    }

    #[Test]
    public function reportListsCheckedReleasesWithTheirAssets(): void
    {
        $diagnostics = self::diagnostics();
        $repository = $diagnostics->addRepository('github', 'owner/repo', '/^app-.*/');
        $repository->matchedReleases = 2;

        $release = $repository->addRelease('v1.2.0');
        $release->registerAssets(['app-1.2.0-darwin-arm64.tar.gz', 'app-1.2.0-windows-amd64.zip']);
        $release->reason = 'no asset matches OS `linux`, architecture `amd64`, name pattern `/^app-.*/`';

        $failed = $repository->addRelease('v1.1.0');
        $failed->registerAssets(['app-1.1.0-linux-amd64.tar.gz']);
        $failed->addFailure('app-1.1.0-linux-amd64.tar.gz', new \RuntimeException('Broken archive'));

        $report = $diagnostics->render();

        Assert::string($report)->contains('2 release(s) match the requested version and stability.');
        Assert::string($report)->contains('Checked releases:');
        Assert::string($report)->contains('- v1.2.0: 2 asset(s), no asset matches OS `linux`');
        Assert::string($report)->contains('Assets: app-1.2.0-darwin-arm64.tar.gz, app-1.2.0-windows-amd64.zip');
        Assert::string($report)->contains('Failed asset `app-1.1.0-linux-amd64.tar.gz`: Broken archive');
    }

    #[Test]
    public function reportContainsRepositoryLevelError(): void
    {
        $diagnostics = self::diagnostics();
        $repository = $diagnostics->addRepository('github', 'owner/repo', '/^app-.*/');
        $repository->error = new ApiException(
            "GitHub API rate limit exceeded.\nSet the GITHUB_TOKEN environment variable.",
            'owner/repo',
        );

        $fallback = $diagnostics->addRepository('gitlab', 'group/app', '/^app-.*/');
        $fallback->matchedReleases = 0;

        $report = $diagnostics->render();

        Assert::string($report)->contains('Tried 2 repository(ies):');
        Assert::string($report)->contains('GitHub API rate limit exceeded.');
        Assert::string($report)->contains('Set the GITHUB_TOKEN environment variable.');
        Assert::string($report)->contains('2) gitlab `group/app`');
    }

    #[Test]
    public function releaseWithoutFetchedAssetListIsNotReportedAsEmpty(): void
    {
        $diagnostics = self::diagnostics();
        $repository = $diagnostics->addRepository('github', 'owner/repo', '/^app-.*/');
        $repository->matchedReleases = 1;

        // An error interrupted the attempt before the asset list was fetched
        $repository->addRelease('v1.2.0')->reason = 'GitHub API is unavailable: HTTP 502 Bad Gateway';

        $report = $diagnostics->render();

        Assert::string($report)->contains('- v1.2.0: asset list not loaded, GitHub API is unavailable');
        Assert::string($report)->notContains('0 asset(s)');
    }

    #[Test]
    public function reportLimitsTheNumberOfDescribedReleases(): void
    {
        $diagnostics = self::diagnostics();
        $repository = $diagnostics->addRepository('github', 'owner/repo', '/^app-.*/');
        $repository->matchedReleases = 8;

        for ($i = 8; $i > 0; --$i) {
            $repository->addRelease("v1.0.{$i}")->registerAssets(["app-1.0.{$i}-linux-amd64.tar.gz"]);
        }

        $report = $diagnostics->render();

        Assert::string($report)->contains('- v1.0.8:');
        Assert::string($report)->contains('- v1.0.6:');
        Assert::string($report)->notContains('- v1.0.5:');
        Assert::string($report)->contains('and 5 more release(s)');
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
