<?php

declare(strict_types=1);

namespace Internal\DLoad\Tests\Unit\Module\Software;

use Internal\DLoad\Module\Config\Schema\Embed\Software;
use Internal\DLoad\Module\Software\Origin;
use Internal\DLoad\Module\Software\ShadowReport;
use Internal\DLoad\Module\Software\SoftwareCollection;
use Internal\DLoad\Service\Logger;
use Symfony\Component\Console\Output\BufferedOutput;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Data\DataSet;
use Testo\Test;

#[Test]
#[Covers(ShadowReport::class)]
final class ShadowReportTest
{
    private BufferedOutput $output;

    public function __construct()
    {
        $this->output = new BufferedOutput();
    }

    public function warnsWhenTheOfficialRegistryShadowsPackageEntries(): void
    {
        $collection = SoftwareCollection::empty()
            ->with(Origin::official(), self::software('gh', 'cli/cli'))
            ->with(Origin::package('acme/framework'), self::software('gh', 'acme/gh'))
            ->with(Origin::package('vendor/cms'), self::software('gh', 'vendor/gh'));

        $this->report($collection, 'gh');

        Assert::string($this->output->fetch())
            ->contains('The official registry shadows `gh` declared by acme/framework, vendor/cms.')
            ->contains('acme/framework#gh or vendor/cms#gh');
    }

    #[DataSet(['cli/cli'], 'the same spelling')]
    #[DataSet(['https://github.com/CLI/cli.git'], 'another spelling')]
    public function staysSilentWhenTheOfficialEntryPointsToTheSameRepositories(string $uri): void
    {
        $collection = SoftwareCollection::empty()
            ->with(Origin::official(), self::software('gh', 'cli/cli'))
            ->with(Origin::package('acme/framework'), self::software('gh', $uri));

        $this->report($collection, 'gh');

        Assert::same($this->output->fetch(), '');
    }

    public function namesOnlyThePackagesThatDifferFromTheOfficialEntry(): void
    {
        $collection = SoftwareCollection::empty()
            ->with(Origin::official(), self::software('gh', 'cli/cli'))
            ->with(Origin::package('acme/framework'), self::software('gh', 'github.com/cli/cli'))
            ->with(Origin::package('vendor/cms'), self::software('gh', 'vendor/gh'));

        $this->report($collection, 'gh');

        Assert::string($this->output->fetch())
            ->contains('The official registry shadows `gh` declared by vendor/cms.')
            ->contains('use vendor/cms#gh.')
            ->notContains('acme/framework');
    }

    public function listsEveryPackageWhenPackagesDeclareDifferentRepositories(): void
    {
        $collection = SoftwareCollection::empty()
            ->with(Origin::package('acme/framework'), self::software('gh', 'cli/cli'))
            ->with(Origin::package('vendor/cms'), self::software('gh', 'vendor/gh-fork'));

        $this->report($collection, 'gh');

        Assert::string($this->output->fetch())
            ->contains('Several packages declare `gh`, using acme/framework#gh.')
            ->contains("  acme/framework#gh    cli/cli\n")
            ->contains('  vendor/cms#gh        vendor/gh-fork');
    }

    #[DataSet([['github' => 'owner/repo'], ['github' => 'https://github.com/Owner/Repo.git']], 'https URL with .git')]
    #[DataSet([['github' => 'owner/repo'], ['github' => 'github.com/owner/repo']], 'host without scheme')]
    #[DataSet([['github' => 'owner/repo'], ['github' => 'https://www.github.com/owner/repo/']], 'www and trailing slash')]
    #[DataSet([['gitlab' => 'group/repo'], ['gitlab' => 'https://gitlab.com/group/repo']], 'public GitLab host')]
    #[DataSet([['github' => 'a/one', 'gitlab' => 'b/two'], ['gitlab' => 'b/two', 'github' => 'a/one']], 'order of repositories')]
    #[DataSet([['github' => 'owner/repo'], ['GitHub' => 'owner/repo']], 'case of the type')]
    public function staysSilentWhenPackagesPointToTheSameRepositories(array $first, array $second): void
    {
        $collection = SoftwareCollection::empty()
            ->with(Origin::package('acme/framework'), self::software('gh', ...self::repositories($first)))
            ->with(Origin::package('vendor/cms'), self::software('gh', ...self::repositories($second)));

        $this->report($collection, 'gh');

        Assert::same($this->output->fetch(), '');
    }

    #[DataSet([['github' => 'owner/repo'], ['gitlab' => 'owner/repo']], 'another type')]
    #[DataSet([['github' => 'owner/repo'], ['github' => 'owner/other']], 'another repository')]
    #[DataSet([['github' => 'owner/repo'], ['github' => 'owner/repo', 'gitlab' => 'owner/repo']], 'an extra repository')]
    #[DataSet([['gitlab' => 'gitlab.example.com/owner/repo'], ['gitlab' => 'owner/repo']], 'a self-hosted host')]
    public function warnsWhenPackagesPointToDifferentRepositories(array $first, array $second): void
    {
        $collection = SoftwareCollection::empty()
            ->with(Origin::package('acme/framework'), self::software('gh', ...self::repositories($first)))
            ->with(Origin::package('vendor/cms'), self::software('gh', ...self::repositories($second)));

        $this->report($collection, 'gh');

        Assert::string($this->output->fetch())->contains('Several packages declare `gh`');
    }

    public function aConfigEntryShadowsSilently(): void
    {
        $collection = SoftwareCollection::empty()
            ->with(Origin::config(), self::software('gh', 'local/gh'))
            ->with(Origin::official(), self::software('gh', 'cli/cli'))
            ->with(Origin::package('acme/framework'), self::software('gh', 'acme/gh'))
            ->with(Origin::package('vendor/cms'), self::software('gh', 'vendor/gh'));

        $this->report($collection, 'gh');

        Assert::same($this->output->fetch(), '');
    }

    public function anExplicitPackageIdIsSilent(): void
    {
        $collection = SoftwareCollection::empty()
            ->with(Origin::official(), self::software('gh', 'cli/cli'))
            ->with(Origin::package('acme/framework'), self::software('gh', 'acme/gh'))
            ->with(Origin::package('vendor/cms'), self::software('gh', 'vendor/gh'));

        $this->report($collection, 'acme/framework#gh');

        Assert::same($this->output->fetch(), '');
    }

    #[DataSet(['gh'], 'a single package entry')]
    #[DataSet(['rr'], 'an official entry without package rivals')]
    #[DataSet(['unknown'], 'an unknown id')]
    public function staysSilentWithoutShadowedPackageEntries(string $id): void
    {
        $collection = SoftwareCollection::empty()
            ->with(Origin::official(), self::software('rr', 'roadrunner-server/roadrunner'))
            ->with(Origin::package('acme/framework'), self::software('gh', 'cli/cli'));

        $this->report($collection, $id);

        Assert::same($this->output->fetch(), '');
    }

    /**
     * @param array<non-empty-string, non-empty-string> $repositories Uri by type.
     * @return list<array{type: non-empty-string, uri: non-empty-string}>
     */
    private static function repositories(array $repositories): array
    {
        $result = [];
        foreach ($repositories as $type => $uri) {
            $result[] = ['type' => $type, 'uri' => $uri];
        }

        return $result;
    }

    /**
     * @param non-empty-string $id
     * @param non-empty-string|array{type: non-empty-string, uri: non-empty-string} ...$repositories
     *        A bare string is a GitHub repository.
     */
    private static function software(string $id, string|array ...$repositories): Software
    {
        return Software::fromArray([
            'name' => $id,
            'repositories' => \array_map(
                static fn(string|array $repository): array => \is_string($repository)
                    ? ['type' => 'github', 'uri' => $repository]
                    : $repository,
                $repositories,
            ),
        ]);
    }

    /**
     * @param non-empty-string $id
     */
    private function report(SoftwareCollection $collection, string $id): void
    {
        (new ShadowReport(new Logger($this->output)))->report($collection, $id);
    }
}
