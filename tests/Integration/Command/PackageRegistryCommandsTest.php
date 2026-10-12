<?php

declare(strict_types=1);

namespace Internal\DLoad\Tests\Integration\Command;

use Internal\DLoad\Command\Get;
use Internal\DLoad\Command\ListSoftware;
use Internal\DLoad\Command\Show;
use Internal\DLoad\Module\Common\FileSystem\FS;
use Internal\Path;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\ApplicationTester;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Core\Exception\SkipTest;
use Testo\Data\DataSet;
use Testo\Filter\Group;
use Testo\Lifecycle\AfterTest;
use Testo\Lifecycle\BeforeTest;
use Testo\Test;

/**
 * Commands on a project with two Composer packages:
 *
 * - `acme/fw` declares `trap` (shadowed by the official registry), `acme-tool` and `php-tool`,
 *   whose binary is a copy of PHP in the tests that need one;
 * - `x/b` declares `acme-tool` too, `evil` with console tags in its text, and `pwn` and `php-pwn`
 *   whose version commands try to run code.
 */
#[Group('integration')]
final class PackageRegistryCommandsTest
{
    private const EVIL_TEXT = [
        'name' => '<error>Evil</error>',
        'description' => '<href=https://evil.example>click</>',
        'homepage' => '<href=https://evil.example>home</>',
        'uri' => '<href=https://evil.example>uri</>',
    ];

    private string $directory;
    private string $cwd;

    /** `DLOAD_CACHE_DIR` before the test, false when it was not set */
    private string|false $cacheDir;

    #[Test]
    #[Covers(ListSoftware::class)]
    public function theSoftwareListShowsOriginsAndShadowedEntries(): void
    {
        $this->config('<registry><software name="Trap" alias="trap" description="Local trap"/></registry>');

        $tester = $this->run(['command' => 'software']);

        Assert::same($tester->getStatusCode(), Command::SUCCESS);
        Assert::string(self::display($tester))
            ->contains("trap  Trap  [dload.xml]\nShadows: official, acme/fw#trap\n")
            ->contains("acme-tool  Acme Tool  [acme/fw]\nShadows: x/b#acme-tool\n")
            ->contains("rr  RoadRunner  [official]\n");
    }

    #[Test]
    #[Covers(Show::class)]
    public function aConfiguredPackageIdIsListedAsConfigured(): void
    {
        $bin = $this->directory . \DIRECTORY_SEPARATOR . 'bin';
        \mkdir($bin);
        \touch($bin . \DIRECTORY_SEPARATOR . self::executable('acme-trap'));
        $this->config(\sprintf('<actions><download software="acme/fw#trap" extract-path="%s"/></actions>', $bin));

        $tester = $this->run(['command' => 'show']);

        Assert::string(self::display($tester))
            ->contains("Configured software:\n  acme/fw#trap (unknown) ")
            ->notContains('No configured software found');
    }

    #[Test]
    #[Covers(Show::class)]
    public function aConfiguredShadowedEntryDoesNotHideTheWinnerOfItsId(): void
    {
        $bin = $this->directory . \DIRECTORY_SEPARATOR . 'bin';
        \mkdir($bin);
        \touch($bin . \DIRECTORY_SEPARATOR . self::executable('xb-tool'));
        \touch($this->directory . \DIRECTORY_SEPARATOR . self::executable('local-tool'));
        $this->config(\sprintf(
            '<registry><software name="Local Tool" alias="acme-tool"><binary name="local-tool"/></software></registry>'
            . '<actions><download software="x/b#acme-tool" extract-path="%s"/></actions>',
            $bin,
        ));
        \chdir($this->directory);

        $tester = $this->run(['command' => 'show']);

        Assert::string(self::display($tester))
            ->contains("Configured software:\n  x/b#acme-tool (unknown) ")
            ->contains("Downloaded software (not configured):\n  acme-tool (unknown) ");
    }

    #[Test]
    #[Covers(Show::class)]
    public function unconfiguredPackageSoftwareIsNotRunToListIt(): void
    {
        $this->copyPhpTo('php-tool');
        \touch($this->directory . \DIRECTORY_SEPARATOR . self::executable('acme-tool'));
        $this->config('');
        \chdir($this->directory);

        $tester = $this->run(['command' => 'show']);

        Assert::string(self::display($tester))
            ->notContains('  php-tool (')
            ->notContains('  acme-tool (')
            ->contains("Downloaded software (not configured):\n  No additional downloaded software found");
    }

    #[Test]
    #[Covers(Show::class)]
    public function theVersionOfConfiguredPackageSoftwareIsShown(): void
    {
        $this->copyPhpTo('php-tool');
        $this->config(\sprintf('<actions><download software="acme/fw#php-tool" extract-path="%s"/></actions>', $this->directory));

        $tester = $this->run(['command' => 'show']);

        Assert::string(self::display($tester))->contains(\sprintf("Configured software:\n  acme/fw#php-tool (%s) ", \PHP_VERSION));
    }

    #[Test]
    #[Covers(Show::class)]
    #[DataSet(['php-tool'], 'the bare id')]
    #[DataSet(['acme/fw#php-tool'], 'the package id')]
    public function theVersionOfRequestedPackageSoftwareIsShown(string $id): void
    {
        $this->copyPhpTo('php-tool');
        $this->config('');
        \chdir($this->directory);

        $tester = $this->run(['command' => 'show', 'software' => $id]);

        Assert::string(self::display($tester))->contains(\sprintf("Version: %s\n", \PHP_VERSION));
    }

    #[Test]
    #[Covers(Show::class)]
    #[DataSet([[]], 'the list')]
    #[DataSet([['--all' => true]], 'the full list')]
    #[DataSet([['software' => 'pwn']], 'a shell injection by id')]
    #[DataSet([['software' => 'x/b#php-pwn']], 'a PHP option injection by package id')]
    public function aPackageVersionCommandThatIsNotOneOptionIsRejected(array $input): void
    {
        $this->copyPhpTo('pwn-tool');
        $this->copyPhpTo('pwn2-tool');
        $this->config('');
        \chdir($this->directory);

        $tester = $this->run(['command' => 'show'] + $input);

        Assert::false(\file_exists($this->directory . '/pwned.txt'));
        Assert::false(\file_exists($this->directory . '/PWNED.txt'));
        Assert::string(self::display($tester))
            ->contains('the version command `--version & echo PWNED>pwned.txt` of `pwn` must be one option like `--version`')
            ->contains("the version command `-r file_put_contents('PWNED.txt','x');` of `php-pwn` must be one option")
            ->notContains('  pwn ')
            ->notContains('  php-pwn ');
    }

    #[Test]
    #[Covers(Show::class)]
    #[DataSet(['acme/fw#trap', 'acme/fw', 'Not included in project config'], 'the shadowed package entry')]
    #[DataSet(['trap', 'official', 'Included in project config'], 'the official entry')]
    public function showTellsTheSourceAndWhetherThatEntryIsConfigured(string $id, string $source, string $config): void
    {
        $this->config('<actions><download software="trap"/></actions>');

        $tester = $this->run(['command' => 'show', 'software' => $id]);

        Assert::same($tester->getStatusCode(), Command::SUCCESS);
        Assert::string(self::display($tester))
            ->contains("Source: $source\n")
            ->contains("Project configuration: $config\n");
    }

    #[Test]
    #[Covers(ListSoftware::class)]
    #[Covers(Show::class)]
    #[DataSet([['command' => 'software'], ['name', 'description', 'homepage', 'uri']], 'the software list')]
    #[DataSet([['command' => 'show', '--all' => true], ['description']], 'the full show list')]
    #[DataSet([['command' => 'show', 'software' => 'evil'], ['name', 'description', 'homepage']], 'the details')]
    public function registryTextIsPrintedLiterally(array $input, array $fields): void
    {
        $this->config('');

        $plain = self::display($this->run($input));
        $decorated = self::display($this->run($input, decorated: true));

        foreach ($fields as $field) {
            Assert::string($plain)->contains(self::EVIL_TEXT[$field]);
            Assert::string($decorated)->contains(self::EVIL_TEXT[$field]);
        }
        Assert::string($decorated)->notContains("\e]8;;");
    }

    #[Test]
    #[Covers(ListSoftware::class)]
    #[Covers(Show::class)]
    #[DataSet([['command' => 'software'], ['C12J', 'before31mafter', 'https://c1.example/31m', 'bit31mbucket: a/b31m']], 'the software list')]
    #[DataSet([['command' => 'show', '--all' => true], ['before31mafter']], 'the full show list')]
    #[DataSet([['command' => 'show', 'software' => 'c1'], ['C12J', 'before31mafter', 'https://c1.example/31m']], 'the details')]
    public function controlCharactersOfRegistryTextAreNotPrinted(array $input, array $texts): void
    {
        $this->config('');

        $display = self::display($this->run($input, decorated: true));

        foreach ($texts as $text) {
            Assert::string($display)->contains($text);
        }
        Assert::string($display)->notContains("\u{9B}");
    }

    #[Test]
    #[Covers(Get::class)]
    #[DataSet([['c1'], 'bit31mbucket'], 'a repository type of a package')]
    #[DataSet([["ev\e[2Jil\u{9B}31m"], 'ev[2Jil31m'], 'a requested id')]
    public function theFailureReportOfGetHasNoControlCharacters(array $software, string $text): void
    {
        $this->config('');

        $display = self::display($this->run(['command' => 'get', 'software' => $software, '--path' => $this->directory . '/bin']));

        Assert::string($display)
            ->contains('Failed to download')
            ->contains($text)
            ->notContains("\u{9B}")
            ->notContains("\e[2J");
    }

    #[BeforeTest]
    protected function prepare(): void
    {
        $this->cwd = (string) \getcwd();
        $this->directory = \sys_get_temp_dir() . \DIRECTORY_SEPARATOR . 'dload-package-registry-' . \bin2hex(\random_bytes(6));
        \mkdir($this->directory . '/vendor/composer', recursive: true);
        \mkdir($this->directory . '/vendor/acme/fw', recursive: true);
        \mkdir($this->directory . '/vendor/x/b', recursive: true);
        \file_put_contents($this->directory . '/composer.json', (string) \json_encode(['require' => ['acme/fw' => '*', 'x/b' => '*']]));
        \file_put_contents($this->directory . '/vendor/composer/installed.json', (string) \json_encode(['packages' => [
            ['name' => 'acme/fw', 'extra' => ['dload' => ['registry' => 'dload.xml']], 'install-path' => '../acme/fw'],
            ['name' => 'x/b', 'extra' => ['dload' => ['registry' => 'dload.xml']], 'install-path' => '../x/b'],
        ]]));
        \file_put_contents($this->directory . '/vendor/acme/fw/dload.xml', <<<'XML'
            <?xml version="1.0"?>
            <dload>
                <registry>
                    <software name="Trap" alias="trap" description="Acme trap">
                        <repository type="github" uri="acme/trap"/>
                        <binary name="acme-trap"/>
                    </software>
                    <software name="Acme Tool" alias="acme-tool">
                        <repository type="github" uri="acme/tool"/>
                        <binary name="acme-tool"/>
                    </software>
                    <software name="PHP Tool" alias="php-tool">
                        <repository type="github" uri="acme/php"/>
                        <binary name="php-tool" version-command="--version"/>
                    </software>
                </registry>
            </dload>
            XML);
        \file_put_contents($this->directory . '/vendor/x/b/dload.xml', <<<'XML'
            <?xml version="1.0"?>
            <dload>
                <registry>
                    <software name="X Tool" alias="acme-tool">
                        <repository type="github" uri="x/tool"/>
                        <binary name="xb-tool"/>
                    </software>
                    <software name="&lt;error&gt;Evil&lt;/error&gt;" alias="evil"
                              description="&lt;href=https://evil.example&gt;click&lt;/&gt;"
                              homepage="&lt;href=https://evil.example&gt;home&lt;/&gt;">
                        <repository type="github" uri="&lt;href=https://evil.example&gt;uri&lt;/&gt;"/>
                        <binary name="evil-tool"/>
                    </software>
                    <software name="Pwn">
                        <repository type="github" uri="x/pwn"/>
                        <binary name="pwn-tool" version-command="--version &amp; echo PWNED&gt;pwned.txt"/>
                    </software>
                    <software name="C1&#x9b;2J" alias="c1" description="before&#x9b;31mafter" homepage="https://c1.example/&#x9b;31m">
                        <repository type="bit&#x9b;31mbucket" uri="a/b&#x9b;31m"/>
                        <binary name="c1-tool"/>
                    </software>
                    <software name="PHP Pwn" alias="php-pwn">
                        <repository type="github" uri="x/php-pwn"/>
                        <binary name="pwn2-tool" version-command="-r file_put_contents('PWNED.txt','x');"/>
                    </software>
                </registry>
            </dload>
            XML);
        $this->cacheDir = \getenv('DLOAD_CACHE_DIR');
        \putenv('DLOAD_CACHE_DIR=' . $this->directory . '/cache');
    }

    #[AfterTest]
    protected function cleanup(): void
    {
        \putenv($this->cacheDir === false ? 'DLOAD_CACHE_DIR' : 'DLOAD_CACHE_DIR=' . $this->cacheDir);
        \chdir($this->cwd);
        \is_dir($this->directory) and FS::removeDir(Path::create($this->directory));
    }

    /**
     * @return string The command output with `\n` line endings: Console writes `PHP_EOL`.
     */
    private static function display(ApplicationTester $tester): string
    {
        return \str_replace(\PHP_EOL, "\n", $tester->getDisplay());
    }

    private static function executable(string $name): string
    {
        return $name . (\PHP_OS_FAMILY === 'Windows' ? '.exe' : '');
    }

    /**
     * Puts a working copy of PHP into the project under the binary name.
     */
    private function copyPhpTo(string $binaryName): void
    {
        $file = $this->directory . \DIRECTORY_SEPARATOR . self::executable($binaryName);
        @\copy(\PHP_BINARY, $file) or throw new SkipTest('Cannot copy the PHP binary.');
        \chmod($file, 0o755);
    }

    private function config(string $content): void
    {
        \file_put_contents($this->directory . '/dload.xml', '<?xml version="1.0"?><dload>' . $content . '</dload>');
    }

    /**
     * @param array<string, mixed> $input
     */
    private function run(array $input, bool $decorated = false): ApplicationTester
    {
        $application = new Application();
        $application->setAutoExit(false);
        $application->setCatchExceptions(false);
        foreach ([new Get(), new ListSoftware(), new Show()] as $command) {
            // Symfony Console 8 renamed `add()` to `addCommand()`
            \method_exists($application, 'addCommand')
                ? $application->addCommand($command)
                : $application->add($command);
        }

        $tester = new ApplicationTester($application);
        $tester->run(
            $input + ['--config' => $this->directory . '/dload.xml'],
            ['interactive' => false, 'decorated' => $decorated],
        );

        return $tester;
    }
}
