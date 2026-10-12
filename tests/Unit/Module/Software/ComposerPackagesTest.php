<?php

declare(strict_types=1);

namespace Internal\DLoad\Tests\Unit\Module\Software;

use Internal\DLoad\Module\Software\ComposerPackages;
use Internal\DLoad\Module\Software\Entry;
use Internal\DLoad\Service\Logger;
use Internal\Path;
use Symfony\Component\Console\Output\BufferedOutput;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Core\Exception\SkipTest;
use Testo\Data\DataSet;
use Testo\Lifecycle\AfterTest;
use Testo\Lifecycle\BeforeTest;
use Testo\Test;

#[Test]
#[Covers(ComposerPackages::class)]
final class ComposerPackagesTest
{
    private string $dir;
    private BufferedOutput $output;

    #[BeforeTest]
    public function createProject(): void
    {
        $this->dir = \sys_get_temp_dir() . \DIRECTORY_SEPARATOR . 'dload-composer-packages-' . \bin2hex(\random_bytes(6));
        \mkdir($this->dir, 0777, true);
        $this->output = new BufferedOutput();
    }

    #[AfterTest]
    public function removeProject(): void
    {
        self::remove($this->dir);
    }

    public function readsTheRootPackageAndDirectDependenciesInPrecedenceOrder(): void
    {
        $this->root([
            'name' => 'acme/app',
            'extra' => ['dload' => ['registry' => 'dload-registry.xml']],
            'require' => ['php' => '^8.1', 'b/second' => '*', 'a/first' => '*'],
            'require-dev' => ['c/dev' => '*'],
        ]);
        $this->file('dload-registry.xml', self::registry('<software name="root-tool"/>'));
        $this->installed(
            $this->package('c/dev', self::registry('<software name="dev-tool"/>')),
            $this->package('a/first', self::registry('<software name="first-tool"/>')),
            $this->package('t/transitive', self::registry('<software name="transitive-tool"/>')),
            $this->package('b/second', self::registry('<software name="second-tool"/><software name="Other" alias="other"/>')),
        );

        $entries = $this->entries();

        Assert::same(self::describe($entries), [
            'acme/app#root-tool',
            'b/second#second-tool',
            'b/second#other',
            'a/first#first-tool',
            'c/dev#dev-tool',
        ]);
        Assert::same($this->output->fetch(), '');
    }

    public function aRootPackageWithoutNameIsNamedRoot(): void
    {
        $this->root(['extra' => ['dload' => ['registry' => 'registry.xml']]]);
        $this->file('registry.xml', self::registry('<software name="tool"/>'));

        Assert::same(self::describe($this->entries()), ['__root__#tool']);
    }

    public function requireMatchesInstalledPackagesWhateverTheCase(): void
    {
        $this->root(['require' => ['Acme/Lib' => '*']]);
        $this->installed($this->package('acme/lib', self::registry('<software name="tool"/>')));

        Assert::same(self::describe($this->entries()), ['acme/lib#tool']);
    }

    public function honoursTheVendorDirOfTheRootPackage(): void
    {
        $this->root(['require' => ['acme/lib' => '*'], 'config' => ['vendor-dir' => 'libs']]);
        $this->installedIn('libs', $this->package('acme/lib', self::registry('<software name="tool"/>'), vendorDir: 'libs'));

        Assert::same(self::describe($this->entries()), ['acme/lib#tool']);
    }

    public function readsOnlyTheRegistrySectionOfTheFile(): void
    {
        $this->root(['require' => ['acme/lib' => '*']]);
        $this->installed($this->package('acme/lib', <<<'XML'
            <?xml version="1.0"?>
            <dload temp-dir="./runtime">
                <actions>
                    <download software="ignored"/>
                </actions>
                <registry>
                    <software name="Tool" alias="tool" description="A tool">
                        <repository type="github" uri="acme/tool" asset-pattern="/^tool-.*/"/>
                        <binary name="tool"/>
                    </software>
                </registry>
            </dload>
            XML));

        $entries = $this->entries();

        Assert::same(self::describe($entries), ['acme/lib#tool']);
        $software = $entries[0]->software;
        Assert::same($software->description, 'A tool');
        Assert::same($software->repositories[0]->uri, 'acme/tool');
        Assert::same($software->repositories[0]->assetPattern, '/^tool-.*/');
        Assert::same($software->binary?->name, 'tool');
    }

    public function packagesWithoutTheDeclarationAreIgnored(): void
    {
        $this->root(['require' => ['acme/lib' => '*', 'acme/plain' => '*', 'acme/missing' => '*']]);
        $this->installed(
            $this->package('acme/plain', null),
            $this->package('acme/lib', self::registry('<software name="tool"/>')),
        );

        Assert::same(self::describe($this->entries()), ['acme/lib#tool']);
        Assert::same($this->output->fetch(), '');
    }

    public function noComposerJsonMeansNoEntries(): void
    {
        Assert::same($this->entries(), []);
        Assert::same($this->output->fetch(), '');
    }

    public function withoutInstalledPackagesOnlyTheRootRegistryIsRead(): void
    {
        $this->root([
            'name' => 'acme/app',
            'extra' => ['dload' => ['registry' => 'registry.xml']],
            'require' => ['acme/lib' => '*'],
        ]);
        $this->file('registry.xml', self::registry('<software name="tool"/>'));

        Assert::same(self::describe($this->entries()), ['acme/app#tool']);
        Assert::same($this->output->fetch(), '');
    }

    public function theRootRegistryThatIsTheLoadedConfigIsNotReadAgain(): void
    {
        $this->root([
            'name' => 'acme/app',
            'extra' => ['dload' => ['registry' => 'dload.xml']],
            'require' => ['acme/lib' => '*'],
        ]);
        $this->file('dload.xml', self::registry('<software name="local"/>'));
        $this->installed($this->package('acme/lib', self::registry('<software name="tool"/>')));

        $entries = $this->entries(configFile: 'dload.xml');

        Assert::same(self::describe($entries), ['acme/lib#tool']);
        Assert::same($this->output->fetch(), '');
    }

    public function theRootRegistryIsReadWhenAnotherConfigIsLoaded(): void
    {
        $this->root(['name' => 'acme/app', 'extra' => ['dload' => ['registry' => 'dload.xml']]]);
        $this->file('dload.xml', self::registry('<software name="tool"/>'));
        $this->file('dload.local.xml', self::registry());

        Assert::same(self::describe($this->entries(configFile: 'dload.local.xml')), ['acme/app#tool']);
    }

    #[DataSet(['missing.xml', 'registry file `missing.xml` not found'], 'a missing file')]
    #[DataSet(['../../outside.xml', 'registry file `../../outside.xml` is outside the package'], 'a path above the package')]
    #[DataSet(['{outside}', 'is outside the package'], 'an absolute path')]
    #[DataSet(['broken.xml', 'is not a valid XML'], 'an invalid XML')]
    #[DataSet([['registry.xml'], '`extra.dload.registry` must be a path to a file'], 'not a string')]
    #[DataSet(['', '`extra.dload.registry` must be a path to a file'], 'an empty string')]
    #[DataSet(["dload.xml\0", '`extra.dload.registry` must be a path to a file'], 'a null byte')]
    #[DataSet(['no-registry.xml', 'has no `<dload><registry>` section'], 'an XML without a registry section')]
    #[DataSet(['foreign.xml', 'has no `<dload><registry>` section'], 'an XML of another format')]
    public function rejectsABrokenDeclarationAndKeepsTheOtherPackages(mixed $registry, string $warning): void
    {
        $this->file('vendor/outside.xml', self::registry('<software name="evil"/>'));
        $registry === '{outside}' and $registry = $this->dir . \DIRECTORY_SEPARATOR . \implode(\DIRECTORY_SEPARATOR, ['vendor', 'outside.xml']);
        $this->root(['require' => ['acme/broken' => '*', 'acme/lib' => '*']]);
        $this->installed(
            $this->package('acme/broken', null, ['dload' => ['registry' => $registry]]),
            $this->package('acme/lib', self::registry('<software name="tool"/>')),
        );
        $this->file('vendor/acme/broken/broken.xml', '<dload><registry>');
        $this->file('vendor/acme/broken/no-registry.xml', '<dload><actions><download software="tool"/></actions></dload>');
        $this->file('vendor/acme/broken/foreign.xml', '<config><registry><software name="evil"/></registry></config>');

        $entries = $this->entries();

        Assert::same(self::describe($entries), ['acme/lib#tool']);
        Assert::string($this->output->fetch())->contains('Package `acme/broken`: ')->contains($warning);
    }

    public function rejectsASymlinkLeadingOutOfThePackage(): void
    {
        $this->file('outside.xml', self::registry('<software name="evil"/>'));
        $this->root(['require' => ['acme/lib' => '*']]);
        $this->installed($this->package('acme/lib', null, ['dload' => ['registry' => 'link.xml']]));
        $link = $this->dir . '/vendor/acme/lib/link.xml';
        if (!@\symlink($this->dir . \DIRECTORY_SEPARATOR . 'outside.xml', $link)) {
            throw new SkipTest('Symlinks cannot be created here.');
        }

        $entries = $this->entries();

        Assert::same($entries, []);
        Assert::string($this->output->fetch())->contains('registry file `link.xml` is outside the package');
    }

    public function acceptsAPackageInstalledThroughASymlink(): void
    {
        $this->file('packages/lib/dload.xml', self::registry('<software name="tool"/>'));
        $this->root(['require' => ['acme/lib' => '*']]);
        $this->installed(['name' => 'acme/lib', 'extra' => ['dload' => ['registry' => 'dload.xml']], 'install-path' => '../acme/lib']);
        \mkdir($this->dir . '/vendor/acme');
        if (!@\symlink($this->dir . \DIRECTORY_SEPARATOR . 'packages' . \DIRECTORY_SEPARATOR . 'lib', $this->dir . '/vendor/acme/lib')) {
            throw new SkipTest('Symlinks cannot be created here.');
        }

        $entries = $this->entries();

        Assert::same(self::describe($entries), ['acme/lib#tool']);
        Assert::same($this->output->fetch(), '');
    }

    #[DataSet(['<software alias="tool"/>','`<software>` has no `name`'], 'no name')]
    #[DataSet(['<software name="tool"><repository type="github"/></software>', 'a `<repository>` of `tool` has no `uri`'], 'a repository without uri')]
    #[DataSet(['<software name="Tool" alias="a#b"/>', 'the id `a#b` cannot be requested'], 'an alias with #')]
    #[DataSet(['<software name="A#B"/>', 'the id `a#b` cannot be requested'], 'a name with #')]
    #[DataSet(['<software name="tool"><binary pattern="/^tool$/"/></software>', 'a `<binary>` of `tool` has no `name`'], 'a binary without name')]
    #[DataSet(['<software name=""/>', '`<software>` has no `name`'], 'an empty name')]
    #[DataSet(['<software name="  "/>', '`<software>` has no `name`'], 'a blank name')]
    #[DataSet(['<software name="Tool" alias=""/>', '`<software>` has an empty id'], 'an empty alias')]
    #[DataSet(['<software name="Tool" alias=" "/>', '`<software>` has an empty id'], 'a blank alias')]
    #[DataSet(['<software name="tool"><repository uri=""/></software>', 'a `<repository>` of `tool` has no `uri`'], 'an empty uri')]
    #[DataSet(['<software name="tool"><binary name=" "/></software>', 'a `<binary>` of `tool` has no `name`'], 'a blank binary name')]
    #[DataSet(['<software name="Tool" alias="a:b"/>', 'the id `a:b` cannot be requested'], 'an alias with :')]
    #[DataSet(['<software name="Tool" alias=" tool"/>', 'the id ` tool` cannot be requested'], 'an alias with a leading space')]
    #[DataSet(['<software name="tool "/>', 'the id `tool ` cannot be requested'], 'a name with a trailing space')]
    #[DataSet(['<software name="tool"><binary name="../x"/></software>', '`../x` of `tool` must be a file name, not a path'], 'a binary name above the destination')]
    #[DataSet(['<software name="tool"><binary name="bin\\x"/></software>', '`bin\\x` of `tool` must be a file name, not a path'], 'a binary name with a backslash')]
    #[DataSet(['<software name="tool"><file rename="a/b"/></software>', '`a/b` of `tool` must be a file name, not a path'], 'a file rename with a slash')]
    #[DataSet(['<software name="tool"><file rename=".."/></software>', '`..` of `tool` must be a file name, not a path'], 'a file rename to the parent')]
    #[DataSet(['<software name="tool"><binary name="tool" pattern="/^tool(/"/></software>', 'the pattern `/^tool(/` of `tool` is not a valid regular expression'], 'an invalid binary pattern')]
    #[DataSet(['<software name="tool"><repository uri="a/b" asset-pattern="not a regex"/></software>', 'the pattern `not a regex` of `tool` is not a valid regular expression'], 'an invalid asset pattern')]
    #[DataSet(['<software name="tool"><file pattern="/[/"/></software>', 'the pattern `/[/` of `tool` is not a valid regular expression'], 'an invalid file pattern')]
    public function rejectsAnInvalidDefinitionAndKeepsTheValidOnes(string $invalid, string $warning): void
    {
        $this->root(['require' => ['acme/lib' => '*']]);
        $this->installed($this->package('acme/lib', self::registry($invalid, '<software name="valid"/>')));

        $entries = $this->entries();

        Assert::same(self::describe($entries), ['acme/lib#valid']);
        Assert::string($this->output->fetch())
            ->contains('Package `acme/lib`: software definition skipped, ')
            ->contains($warning);
    }

    #[DataSet(['composer.json', '{"name": '], 'an invalid root composer.json')]
    #[DataSet(['vendor/composer/installed.json', '{"packages": ['], 'an invalid installed.json')]
    public function rejectsInvalidJson(string $file, string $content): void
    {
        $this->root(['require' => ['acme/lib' => '*']]);
        $this->installed($this->package('acme/lib', self::registry('<software name="tool"/>')));
        $this->file($file, $content);

        $entries = $this->entries();

        Assert::same($entries, []);
        Assert::string($this->output->fetch())->contains('is not valid JSON');
    }

    public function acceptsADefinitionThatOmitsOptionalAttributes(): void
    {
        $this->root(['require' => ['acme/lib' => '*']]);
        $this->installed($this->package('acme/lib', self::registry(
            '<software name="tool"><repository uri="acme/tool"/><binary name="tool"/><file/></software>',
        )));

        $entries = $this->entries();

        Assert::same(self::describe($entries), ['acme/lib#tool']);
        Assert::same($this->output->fetch(), '');
    }

    #[DataSet(['--version'], 'a long option')]
    #[DataSet(['--short-version'], 'a long option with a dash')]
    #[DataSet(['-v'], 'a short option')]
    #[DataSet(['-V'], 'an upper-case short option')]
    #[DataSet(['version'], 'a subcommand')]
    public function acceptsAVersionCommandOfOneOption(string $command): void
    {
        $this->root(['require' => ['acme/lib' => '*']]);
        $this->installed($this->package('acme/lib', self::registry(
            \sprintf('<software name="tool"><binary name="tool" version-command="%s"/></software>', $command),
        )));

        $entries = $this->entries();

        Assert::same($entries[0]->software->binary?->versionCommand, $command);
        Assert::same($this->output->fetch(), '');
    }

    #[DataSet(['version --short'], 'two arguments')]
    #[DataSet(['-r file_put_contents(1,2);'], 'PHP code')]
    #[DataSet(['-r1'], 'an option with a value')]
    #[DataSet(['--version=1'], 'an option with an assigned value')]
    #[DataSet(['---version'], 'three dashes')]
    #[DataSet(['--'], 'dashes only')]
    #[DataSet(['-'], 'one dash')]
    #[DataSet(['self-update'], 'a subcommand that changes the binary')]
    #[DataSet(['reset'], 'a subcommand that changes state')]
    #[DataSet(['serve'], 'a subcommand that keeps running')]
    #[DataSet(['Version'], 'the version subcommand in another case')]
    #[DataSet(['versions'], 'a longer subcommand')]
    #[DataSet(['v2'], 'a digit')]
    #[DataSet(['/?'], 'a Windows switch')]
    #[DataSet(['--version&id'], 'a shell operator')]
    #[DataSet(['$(id)'], 'a command substitution')]
    #[DataSet([''], 'empty')]
    public function rejectsAVersionCommandOfMoreThanOneOption(string $command): void
    {
        $this->root(['require' => ['acme/lib' => '*']]);
        $this->installed($this->package('acme/lib', self::registry(
            \sprintf('<software name="tool"><binary name="tool" version-command="%s"/></software>', \htmlspecialchars($command)),
            '<software name="valid"/>',
        )));

        $entries = $this->entries();

        Assert::same(self::describe($entries), ['acme/lib#valid']);
        Assert::string($this->output->fetch())
            ->contains(\sprintf('the version command `%s` of `tool` must be one option like `--version`', $command));
    }

    #[DataSet(['acme/lib'], 'a string')]
    #[DataSet([true], 'a boolean')]
    #[DataSet([42], 'a number')]
    public function rejectsAnExtraDloadThatIsNotAnObject(mixed $dload): void
    {
        $this->root(['require' => ['acme/broken' => '*', 'acme/lib' => '*']]);
        $this->installed(
            $this->package('acme/broken', null, ['dload' => $dload]),
            $this->package('acme/lib', self::registry('<software name="tool"/>')),
        );

        $entries = $this->entries();

        Assert::same(self::describe($entries), ['acme/lib#tool']);
        Assert::string($this->output->fetch())->contains('Package `acme/broken`: `extra.dload` must be an object');
    }

    public function skipsAPackageWithANullByteInItsInstallPath(): void
    {
        $this->root(['require' => ['acme/broken' => '*', 'acme/lib' => '*']]);
        $broken = $this->package('acme/broken', self::registry('<software name="evil"/>'));
        $broken['install-path'] = "../acme/broken\0/../broken";
        $this->installed($broken, $this->package('acme/lib', self::registry('<software name="tool"/>')));

        $entries = $this->entries();

        Assert::same(self::describe($entries), ['acme/lib#tool']);
    }

    #[DataSet(['composer.json', '"acme/app"'], 'a root composer.json string')]
    #[DataSet(['composer.json', 'null'], 'a root composer.json null')]
    #[DataSet(['vendor/composer/installed.json', '"packages"'], 'an installed.json string')]
    #[DataSet(['vendor/composer/installed.json', '42'], 'an installed.json number')]
    public function rejectsJsonThatIsNotAnObject(string $file, string $content): void
    {
        $this->root(['require' => ['acme/lib' => '*']]);
        $this->installed($this->package('acme/lib', self::registry('<software name="tool"/>')));
        $this->file($file, $content);

        $entries = $this->entries();

        Assert::same($entries, []);
        Assert::string($this->output->fetch())->contains('is not a JSON object');
    }

    #[DataSet([['libs']], 'a list')]
    #[DataSet([['dir' => 'libs']], 'an object')]
    #[DataSet([42], 'a number')]
    #[DataSet([''], 'an empty string')]
    #[DataSet(["libs\0"], 'a null byte')]
    public function rejectsAVendorDirThatIsNotAPath(mixed $vendorDir): void
    {
        $this->root([
            'name' => 'acme/app',
            'extra' => ['dload' => ['registry' => 'registry.xml']],
            'require' => ['acme/lib' => '*'],
            'config' => ['vendor-dir' => $vendorDir],
        ]);
        $this->file('registry.xml', self::registry('<software name="root-tool"/>'));
        $this->installed($this->package('acme/lib', self::registry('<software name="tool"/>')));
        $phpErrors = [];
        \set_error_handler(static function (int $level, string $message) use (&$phpErrors): bool {
            $phpErrors[] = $message;
            return true;
        });

        try {
            $entries = $this->entries();
        } finally {
            \restore_error_handler();
        }

        Assert::same(self::describe($entries), ['acme/app#root-tool']);
        Assert::same($phpErrors, []);
        Assert::string($this->output->fetch())->contains('`config.vendor-dir` is not a path');
    }

    #[DataSet(['libs', 'libs'], 'the override')]
    #[DataSet(['', 'other'], 'the config when the override is empty')]
    #[DataSet([null, 'other'], 'the config without an override')]
    public function theVendorDirOverrideWinsOverTheConfig(?string $override, string $used): void
    {
        $this->root(['require' => ['acme/lib' => '*'], 'config' => ['vendor-dir' => 'other']]);
        $this->installedIn('libs', $this->package('acme/lib', self::registry('<software name="from-libs"/>'), vendorDir: 'libs'));
        $this->installedIn('other', $this->package('acme/lib', self::registry('<software name="from-other"/>'), vendorDir: 'other'));

        $entries = $this->entries(vendorDir: $override);

        Assert::same(self::describe($entries), ["acme/lib#from-$used"]);
    }

    public function theOverrideIsUsedWhenTheConfigVendorDirIsBroken(): void
    {
        $this->root(['require' => ['acme/lib' => '*'], 'config' => ['vendor-dir' => ['broken']]]);
        $this->installedIn('libs', $this->package('acme/lib', self::registry('<software name="tool"/>'), vendorDir: 'libs'));

        $entries = $this->entries(vendorDir: 'libs');

        Assert::same(self::describe($entries), ['acme/lib#tool']);
        Assert::same($this->output->fetch(), '');
    }

    public function aPackageInBothRequireAndRequireDevKeepsItsRequirePlace(): void
    {
        $this->root([
            'require' => ['b/both' => '*', 'a/other' => '*'],
            'require-dev' => ['c/dev' => '*', 'B/Both' => '*'],
        ]);
        $this->installed(
            $this->package('a/other', self::registry('<software name="other"/>')),
            $this->package('b/both', self::registry('<software name="both"/>')),
            $this->package('c/dev', self::registry('<software name="dev"/>')),
        );

        $entries = $this->entries();

        Assert::same(self::describe($entries), ['b/both#both', 'a/other#other', 'c/dev#dev']);
    }

    public function rejectsTheComposer1FormatOfInstalledJson(): void
    {
        $this->root(['name' => 'acme/app', 'extra' => ['dload' => ['registry' => 'registry.xml']], 'require' => ['acme/lib' => '*']]);
        $this->file('registry.xml', self::registry('<software name="root-tool"/>'));
        $package = $this->package('acme/lib', self::registry('<software name="tool"/>'));
        $this->file('vendor/composer/installed.json', (string) \json_encode([$package]));

        $entries = $this->entries();

        Assert::same(self::describe($entries), ['acme/app#root-tool']);
        Assert::string($this->output->fetch())->contains('is not in the Composer 2 format');
    }

    private static function registry(string ...$software): string
    {
        return '<?xml version="1.0"?><dload><registry>' . \implode('', $software) . '</registry></dload>';
    }

    /**
     * @param list<Entry> $entries
     * @return list<string> `<origin>#<id>` of each entry.
     */
    private static function describe(array $entries): array
    {
        return \array_map(static fn(Entry $entry): string => $entry->origin . '#' . $entry->getId(), $entries);
    }

    /**
     * Not {@see \Internal\DLoad\Module\Common\FileSystem\FS::removeDir()}: it descends into a symlinked directory, and the symlink tests make one.
     */
    private static function remove(string $path): void
    {
        if (\is_link($path) || \is_file($path)) {
            @\unlink($path) or @\rmdir($path);
            return;
        }

        if (!\is_dir($path)) {
            return;
        }

        foreach (\scandir($path) ?: [] as $item) {
            $item === '.' || $item === '..' or self::remove($path . \DIRECTORY_SEPARATOR . $item);
        }

        @\rmdir($path);
    }

    /**
     * @param non-empty-string|null $configFile Relative to the project.
     * @return list<Entry>
     */
    private function entries(?string $configFile = null, ?string $vendorDir = null): array
    {
        $packages = new ComposerPackages(
            Path::create($this->dir),
            $configFile === null ? null : Path::create($this->dir)->join($configFile),
            new Logger($this->output),
            $vendorDir,
        );

        return $packages->entries();
    }

    private function root(array $composer): void
    {
        $this->file('composer.json', (string) \json_encode($composer));
    }

    /**
     * Writes the package into the vendor directory.
     *
     * @param string|null $registry Content of `dload.xml` of the package, declared in `extra` unless `$extra` is given.
     * @return array The package's record for `installed.json`.
     */
    private function package(string $name, ?string $registry, ?array $extra = null, string $vendorDir = 'vendor'): array
    {
        \mkdir($this->dir . "/$vendorDir/$name", 0777, true);
        $registry === null or $this->file("$vendorDir/$name/dload.xml", $registry);
        $extra ??= $registry === null ? [] : ['dload' => ['registry' => 'dload.xml']];

        return ['name' => $name, 'version' => '1.0.0', 'extra' => $extra, 'install-path' => "../$name"];
    }

    private function installed(array ...$packages): void
    {
        $this->installedIn('vendor', ...$packages);
    }

    private function installedIn(string $vendorDir, array ...$packages): void
    {
        $this->file("$vendorDir/composer/installed.json", (string) \json_encode(['packages' => $packages, 'dev' => true]));
    }

    private function file(string $path, string $content): void
    {
        $full = $this->dir . '/' . $path;
        \is_dir(\dirname($full)) or \mkdir(\dirname($full), 0777, true);
        \file_put_contents($full, $content);
    }
}
