<?php

declare(strict_types=1);

namespace Internal\DLoad\Tests\Unit\Module\Installer;

use Internal\DLoad\Module\Archive\ArchiveFactory;
use Internal\DLoad\Module\Binary\Binary;
use Internal\DLoad\Module\Binary\BinaryProvider;
use Internal\DLoad\Module\Common\FileSystem\FS;
use Internal\DLoad\Module\Common\OperatingSystem;
use Internal\DLoad\Module\Config\Schema\Action\Type;
use Internal\DLoad\Module\Config\Schema\Embed\Software;
use Internal\DLoad\Module\Downloader\Exception\NothingExtracted;
use Internal\DLoad\Module\Downloader\Task\DownloadResult;
use Internal\DLoad\Module\Installer\Installer;
use Internal\DLoad\Module\Installer\Internal\RuleExtraction;
use Internal\DLoad\Module\Installer\Internal\Step\ArchiveStep;
use Internal\DLoad\Module\Installer\Internal\Step\PharStep;
use Internal\DLoad\Module\Installer\Internal\Step\PlainFileStep;
use Internal\DLoad\Module\Version\Version;
use Internal\DLoad\Service\Logger;
use Internal\DLoad\Tests\Unit\Module\Installer\Stub\ListedArchive;
use Internal\Path;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Core\Exception\SkipTest;
use Testo\Data\DataProvider;
use Testo\Data\DataSet;
use Testo\Expect;
use Testo\Lifecycle\AfterTest;
use Testo\Lifecycle\BeforeTest;
use Testo\Test;

#[Covers(Installer::class)]
#[Covers(PharStep::class)]
#[Covers(ArchiveStep::class)]
#[Covers(PlainFileStep::class)]
#[Covers(RuleExtraction::class)]
final class InstallerTest
{
    /** A zip with `pkg-1.0/bin/app`, `pkg-1.0/lib/app/libphp.so` and `pkg-1.0/share/app/VERSION.txt`. */
    private const NESTED_ZIP = __DIR__ . '/../../../Integration/Module/Archive/Fixture/nested.zip';

    private Path $dir;
    private Path $destination;
    private BufferedOutput $log;

    public static function provideNestedArchives(): \Generator
    {
        yield 'zip' => [self::NESTED_ZIP];
        yield 'tar.gz' => [__DIR__ . '/../../../Integration/Module/Archive/Fixture/nested.tar.gz'];
        yield 'tgz' => [__DIR__ . '/../../../Integration/Module/Archive/Fixture/nested.tgz'];
        yield 'tar' => [__DIR__ . '/../../../Integration/Module/Archive/Fixture/nested.tar'];
    }

    #[Test]
    public function pharIsMovedIntoTheDestinationAsIs(): void
    {
        $download = $this->download('tool.phar', '<?php echo 1;');
        $software = Software::fromArray(['name' => 'tool', 'binary' => ['name' => 'tool']]);

        $result = $this->installer()->install($download, $software, Type::Phar, $this->destination, removeDownload: true);

        Assert::same(self::paths($result->files), [(string) $this->destination->join('tool.phar')]);
        Assert::same(\file_get_contents((string) $this->destination->join('tool.phar')), '<?php echo 1;');
        Assert::false($download->file->isFile());
    }

    #[Test]
    public function softwareWithoutRulesIsMovedIntoTheDestinationAsIs(): void
    {
        $download = $this->download('data.bin', 'payload');

        $result = $this->installer()->install($download, Software::fromArray(['name' => 'data']), null, $this->destination, removeDownload: true);

        Assert::same(self::paths($result->files), [(string) $this->destination->join('data.bin')]);
        Assert::false($download->file->isFile());
    }

    #[Test]
    public function archiveIsExtractedPreservingItsStructure(): void
    {
        $download = $this->downloadOf(self::NESTED_ZIP);

        $result = $this->installer()->install($download, Software::fromArray(['name' => 'pkg']), Type::Archive, $this->destination, removeDownload: true);

        Assert::same(\count($result->files), 3);
        Assert::true($this->destination->join('bin', 'app')->isFile());
        Assert::true($this->destination->join('lib', 'app', 'libphp.so')->isFile());
        Assert::true($this->destination->join('share', 'app', 'VERSION.txt')->isFile());
        Assert::false($this->destination->join('pkg-1.0')->exists());
    }

    #[Test]
    public function archiveFiltersKeepTheMatchedFilesAndTheBinaryInPlace(): void
    {
        $download = $this->downloadOf(self::NESTED_ZIP);
        $software = Software::fromArray([
            'name' => 'App',
            'binary' => ['name' => 'app'],
            'files' => [['pattern' => '/^libphp\.so$/']],
        ]);
        $binary = \Mockery::mock(Binary::class);

        $binaryProvider = \Mockery::mock(BinaryProvider::class);
        $binaryProvider->expects('getLocalBinary')
            ->with(
                \Mockery::on(fn(Path $dir): bool => (string) $dir === (string) $this->destination->join('bin')),
                $software->binary,
            )
            ->andReturn($binary);

        $result = $this->installer($binaryProvider)->install($download, $software, Type::Archive, $this->destination, removeDownload: true);

        Assert::same(self::paths($result->files), [
            (string) $this->destination->join('bin', 'app'),
            (string) $this->destination->join('lib', 'app', 'libphp.so'),
        ]);
        Assert::same($result->binary, $binary);
        Assert::false($this->destination->join('share')->exists());
    }

    #[Test]
    public function archiveEntriesEscapingTheDestinationAreSkipped(): void
    {
        $download = $this->download('evil.stub', '');
        $archiveFactory = new ArchiveFactory();
        $archiveFactory->extend(
            static fn(\SplFileInfo $file): ?ListedArchive => $file->getExtension() === 'stub'
                ? new ListedArchive(['pkg/../escaped.txt' => 'evil', 'pkg/kept.txt' => 'good'])
                : null,
            ['stub'],
        );

        $result = $this->installer(archiveFactory: $archiveFactory)
            ->install($download, Software::fromArray(['name' => 'pkg']), Type::Archive, $this->destination, removeDownload: true);

        Assert::same(self::paths($result->files), [(string) $this->destination->join('kept.txt')]);
        Assert::false($this->dir->join('escaped.txt')->exists());
    }

    #[Test]
    public function archiveWithNothingMatchingTheFiltersFails(): void
    {
        $download = $this->downloadOf(self::NESTED_ZIP);
        $software = Software::fromArray(['name' => 'pkg', 'files' => [['pattern' => '/^missing$/']]]);

        Expect::exception(NothingExtracted::class);

        $this->installer()->install($download, $software, Type::Archive, $this->destination, removeDownload: true);
    }

    #[Test]
    public function ruleRenameKeepsTheFileExtension(): void
    {
        $download = $this->downloadOf(self::NESTED_ZIP);
        $software = Software::fromArray(['name' => 'pkg', 'files' => [['pattern' => '/^libphp\.so$/', 'rename' => 'php']]]);

        $result = $this->installer()->install($download, $software, null, $this->destination, removeDownload: true);

        Assert::same(self::paths($result->files), [(string) $this->destination->join('php.so')]);
    }

    #[Test]
    public function rulesExtractTheMatchedEntriesFlat(): void
    {
        $download = $this->downloadOf(self::NESTED_ZIP);
        $software = Software::fromArray(['name' => 'pkg', 'files' => [['pattern' => '/^libphp\.so$/']]]);

        $result = $this->installer()->install($download, $software, null, $this->destination, removeDownload: true);

        Assert::same(self::paths($result->files), [(string) $this->destination->join('libphp.so')]);
        Assert::false($this->destination->join('lib')->exists());
    }

    #[Test]
    public function downloadIsRemovedEvenWhenNothingIsExtracted(): void
    {
        $download = $this->downloadOf(self::NESTED_ZIP);
        $software = Software::fromArray(['name' => 'pkg', 'files' => [['pattern' => '/^missing$/']]]);

        try {
            $this->installer()->install($download, $software, null, $this->destination, removeDownload: true);
            Assert::fail('Nothing matched the rules, the installation must fail.');
        } catch (NothingExtracted) {
        }

        Assert::false($download->file->isFile());
    }

    #[Test]
    public function pharWithoutExtractionRulesIsStillInstalledAsAPhar(): void
    {
        $download = $this->download('tool.phar', '<?php echo 1;');

        $this->installer()->install($download, Software::fromArray(['name' => 'tool']), Type::Phar, $this->destination, removeDownload: true);

        Assert::string($this->log->fetch())->contains('as a PHAR archive');
    }

    #[DataProvider('provideNestedArchives')]
    #[Test]
    public function binaryIsExtractedUnderItsNameAndLocated(string $fixture): void
    {
        $download = $this->downloadOf($fixture);
        $software = Software::fromArray(['name' => 'App', 'binary' => ['name' => 'app']]);
        $binary = \Mockery::mock(Binary::class);

        $binaryProvider = \Mockery::mock(BinaryProvider::class);
        $binaryProvider->expects('getLocalBinary')
            ->with(
                \Mockery::on(fn(Path $dir): bool => (string) $dir === (string) $this->destination),
                $software->binary,
            )
            ->andReturn($binary);

        $result = $this->installer($binaryProvider)->install($download, $software, null, $this->destination, removeDownload: true);

        Assert::same(self::paths($result->files), [(string) $this->destination->join('app')]);
        Assert::same(\file_get_contents((string) $this->destination->join('app')), "binary\n");
        Assert::same($result->binary, $binary);
    }

    #[Test]
    public function downloadIsKeptWhenItIsNotToBeRemoved(): void
    {
        $download = $this->downloadOf(self::NESTED_ZIP);
        $software = Software::fromArray(['name' => 'pkg', 'files' => [['pattern' => '/^app$/']]]);

        $this->installer()->install($download, $software, null, $this->destination, removeDownload: false);

        Assert::true($download->file->isFile());
        Assert::true($this->destination->join('app')->isFile());
    }

    #[Test]
    #[DataSet(['a.b', ['pkg/axb' => 'wrong', 'pkg/a.b' => 'right']], 'a dot')]
    #[DataSet(['c++', ['pkg/ccc' => 'wrong', 'pkg/c++' => 'right']], 'a quantifier')]
    #[DataSet(['t(1)', ['pkg/t1' => 'wrong', 'pkg/t(1)' => 'right']], 'a group')]
    public function theBinaryNameMatchesOnlyItself(string $name, array $entries): void
    {
        $download = $this->download('tool.stub', '');
        $software = Software::fromArray(['name' => 'Tool', 'binary' => ['name' => $name]]);

        $result = $this->installer(archiveFactory: self::listed($entries))
            ->install($download, $software, null, $this->destination, removeDownload: true);

        Assert::same(
            \array_map(static fn(Path $file): string => (string) \file_get_contents((string) $file), [...$result->files]),
            ['right'],
        );
    }

    #[Test]
    #[DataSet(['a.b', 'pkg/a.b', 'a.b'], 'a dotted name')]
    #[DataSet(['A.B', 'pkg/a.b', 'A.B'], 'a dotted name in another case')]
    #[DataSet(['a.b', 'pkg/a.b.exe', 'a.b.exe'], 'a dotted name with an executable extension')]
    #[DataSet(['rr', 'pkg/roadrunner.exe', 'rr.exe'], 'a renamed executable')]
    public function theBinaryKeepsItsNameAndGainsOnlyAMissingExtension(string $name, string $entry, string $installed): void
    {
        $download = $this->download('tool.stub', '');
        $software = Software::fromArray(['name' => 'Tool', 'binary' => ['name' => $name, 'pattern' => '/./']]);

        $result = $this->installer(archiveFactory: self::listed([$entry => 'binary']))
            ->install($download, $software, null, $this->destination, removeDownload: true);

        Assert::same(self::paths($result->files), [(string) $this->destination->join($installed)]);
    }

    #[Test]
    public function aBinaryNameWithConsoleTagsIsInstalledAndPrintedLiterally(): void
    {
        \PHP_OS_FAMILY === 'Windows' and throw new SkipTest('A Windows file name cannot contain `<` and `>`.');
        $name = 'x<fg=nope>y';
        $download = $this->download('tool.stub', '');
        $software = Software::fromArray(['name' => 'Tool', 'binary' => ['name' => $name, 'pattern' => '/./']]);
        $output = new BufferedOutput(decorated: true);

        $this->installer(archiveFactory: self::listed(['pkg/app' => 'binary']), output: $output)
            ->install($download, $software, null, $this->destination, removeDownload: true);

        Assert::string($output->fetch())->contains($name);
        Assert::true(\is_executable((string) $this->destination->join($name)));
    }

    #[Test]
    #[DataSet([['files' => [['pattern' => '/^app$/', 'rename' => '../escaped']]]], 'a rename above the destination')]
    #[DataSet([['files' => [['pattern' => '/^app$/', 'rename' => 'sub/app']]]], 'a rename into a subdirectory')]
    #[DataSet([['files' => [['pattern' => '/^app$/', 'rename' => 'C:app']]]], 'a drive-relative rename')]
    #[DataSet([['binary' => ['name' => '../escaped', 'pattern' => '/^app$/']]], 'a binary name above the destination')]
    #[DataSet([['files' => [['pattern' => '/^app$/', 'rename' => 'nul']]]], 'a rename to a Windows device')]
    public function aTargetNameThatIsAPathFailsTheInstallation(array $rules): never
    {
        $download = $this->download('tool.stub', '');
        $software = Software::fromArray(['name' => 'Tool'] + $rules);

        Expect::exception(\RuntimeException::class)->withMessageContaining('the name must be a file name, not a path');

        $this->installer(archiveFactory: self::listed(['pkg/app' => 'evil']))
            ->install($download, $software, null, $this->destination, removeDownload: true);
    }

    #[Test]
    public function anEntryKeepsItsOwnNameWithoutARename(): void
    {
        \PHP_OS_FAMILY === 'Windows' and throw new SkipTest('A file name cannot contain `:` on Windows.');
        $download = $this->download('tool.stub', '');
        $software = Software::fromArray(['name' => 'Tool', 'files' => [['pattern' => '/^foo:bar$/']]]);

        $result = $this->installer(archiveFactory: self::listed(['pkg/foo:bar' => 'content']))
            ->install($download, $software, null, $this->destination, removeDownload: true);

        Assert::same(self::paths($result->files), [(string) $this->destination->join('foo:bar')]);
    }

    #[BeforeTest]
    protected function prepare(): void
    {
        $this->dir = Path::create(\sys_get_temp_dir())->join('dload-installer-' . \bin2hex(\random_bytes(6)));
        $this->destination = $this->dir->join('destination');
        $this->log = new BufferedOutput(OutputInterface::VERBOSITY_VERY_VERBOSE);
        FS::mkdir($this->dir);
    }

    #[AfterTest]
    protected function cleanup(): void
    {
        FS::remove($this->dir);
    }

    /**
     * @param iterable<Path> $files
     * @return list<string>
     */
    private static function paths(iterable $files): array
    {
        $result = [];
        foreach ($files as $file) {
            $result[] = (string) Path::create((string) $file);
        }

        return $result;
    }

    /**
     * @param array<non-empty-string, string> $entries Content by entry path.
     * @return ArchiveFactory Opens any `.stub` file as an archive of the entries.
     */
    private static function listed(array $entries): ArchiveFactory
    {
        $archiveFactory = new ArchiveFactory();
        $archiveFactory->extend(
            static fn(\SplFileInfo $file): ?ListedArchive => $file->getExtension() === 'stub' ? new ListedArchive($entries) : null,
            ['stub'],
        );

        return $archiveFactory;
    }

    private function installer(
        ?BinaryProvider $binaryProvider = null,
        ArchiveFactory $archiveFactory = new ArchiveFactory(),
        OutputInterface $output = new BufferedOutput(),
    ): Installer {
        if ($binaryProvider === null) {
            $binaryProvider = \Mockery::mock(BinaryProvider::class);
            $binaryProvider->allows('getLocalBinary')->andReturnNull();
        }

        return new Installer(new Logger($this->log), $output, $archiveFactory, $binaryProvider, OperatingSystem::Linux);
    }

    /**
     * @param non-empty-string $name
     */
    private function download(string $name, string $content): DownloadResult
    {
        $file = $this->dir->join($name);
        \file_put_contents((string) $file, $content);

        return new DownloadResult(new \SplFileInfo((string) $file), Version::fromVersionString('1.0.0'));
    }

    /**
     * Copies a fixture, so the installation may move or remove it.
     */
    private function downloadOf(string $fixture): DownloadResult
    {
        return $this->download(\basename($fixture), (string) \file_get_contents($fixture));
    }
}
