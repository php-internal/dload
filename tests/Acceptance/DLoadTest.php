<?php

declare(strict_types=1);

namespace Internal\DLoad\Tests\Acceptance;

use Internal\DLoad\Bootstrap;
use Internal\DLoad\DLoad;
use Internal\DLoad\Module\Common\OperatingSystem;
use Internal\DLoad\Module\Config\Schema\Action\Download as DownloadConfig;
use Internal\DLoad\Module\Config\Schema\Action\Type;
use Internal\DLoad\Service\Logger;
use Internal\Path;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\StyleInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Lifecycle\AfterTest;
use Testo\Lifecycle\BeforeTest;
use Testo\Test;

/**
 * Acceptance tests for DLoad class.
 *
 * Tests the complete download workflow using real repositories and file extraction.
 * Requires internet connectivity to download actual software packages.
 */
#[Covers(DLoad::class)]
final class DLoadTest
{
    private Path $testRuntimeDir;
    private Path $tempDir;
    private Path $destinationDir;
    private DLoad $dload;

    #[Test]
    public function downloadsTrapPharSuccessfully(): void
    {
        $downloadConfig = new DownloadConfig();
        $downloadConfig->software = 'trap';
        $downloadConfig->version = '1.13.16';
        $downloadConfig->type = Type::Phar;
        $downloadConfig->extractPath = (string) $this->destinationDir;

        $this->dload->addTask($downloadConfig);
        $this->dload->run();

        // Assert - Check that trap.phar was downloaded
        $expectedPharPath = (string) $this->destinationDir->join('trap.phar');
        Assert::true(\file_exists($expectedPharPath), 'Trap PHAR should be downloaded to destination directory');

        // Verify the file is not empty
        Assert::int(\filesize($expectedPharPath))->greaterThan(1024, 'Downloaded PHAR should have substantial size');

        // Verify file permissions (should be executable)
        if (PHP_OS_FAMILY !== 'Windows') {
            Assert::true(\is_executable($expectedPharPath), 'PHAR file should be executable');
        }
    }

    #[Test]
    public function downloadsTrapPharSuccessfullyWithForceOption(): void
    {
        $downloadConfig = new DownloadConfig();
        $downloadConfig->software = 'trap';
        $downloadConfig->version = '1.13.16';
        $downloadConfig->type = Type::Phar;
        $downloadConfig->extractPath = (string) $this->destinationDir;

        $this->dload->addTask($downloadConfig);
        $this->dload->run();
        $this->dload->addTask($downloadConfig, true);
        $this->dload->run();

        // Assert - Check that trap.phar was downloaded
        $expectedPharPath = (string) $this->destinationDir->join('trap.phar');
        Assert::true(\file_exists($expectedPharPath), 'Trap PHAR should be downloaded to destination directory');

        // Verify the file is not empty
        Assert::int(\filesize($expectedPharPath))->greaterThan(1024, 'Downloaded PHAR should have substantial size');

        // Verify file permissions (should be executable)
        if (PHP_OS_FAMILY !== 'Windows') {
            Assert::true(\is_executable($expectedPharPath), 'PHAR file should be executable');
        }
    }

    #[Test]
    public function downloadsTomlTestGzBinary(): void
    {
        $dload = $this->buildDLoad($this->createTomlTestXmlConfig());
        $downloadConfig = new DownloadConfig();
        $downloadConfig->software = 'toml-test';
        $downloadConfig->version = '2.1.0';
        $downloadConfig->type = Type::Binary;
        $downloadConfig->extractPath = (string) $this->destinationDir;

        $dload->addTask($downloadConfig);
        $dload->run();

        // Assert - Check that toml-test binary was downloaded and extracted from .gz
        $os = OperatingSystem::fromGlobals();
        $expectedPath = (string) $this->destinationDir->join('toml-test' . $os->getBinaryExtension());
        Assert::true(\file_exists($expectedPath), 'toml-test binary should be downloaded and extracted from .gz archive');
        Assert::int(\filesize($expectedPath))->greaterThan(1024, 'Downloaded binary should have substantial size');

        if (\PHP_OS_FAMILY !== 'Windows') {
            Assert::true(\is_executable($expectedPath), 'Binary file should be executable');
        }
    }

    #[Test]
    public function downloadsTrapBinary(): void
    {
        $downloadConfig = new DownloadConfig();
        $downloadConfig->software = 'trap';
        $downloadConfig->version = '1.13.16';
        $downloadConfig->type = Type::Binary;
        $downloadConfig->extractPath = (string) $this->destinationDir;

        $this->dload->addTask($downloadConfig);
        $this->dload->run();

        // Assert - Check that Trap binary was downloaded and extracted
        $os = OperatingSystem::fromGlobals();
        $expectedPharPath = (string) $this->destinationDir->join('trap' . $os->getBinaryExtension());
        Assert::true(\file_exists($expectedPharPath), 'Trap binary should be downloaded to destination directory');

        // Verify the file is not empty
        Assert::int(\filesize($expectedPharPath))->greaterThan(1024, 'Downloaded binary should have substantial size');

        Assert::true(\is_executable($expectedPharPath), 'Binary file should be executable');
    }

    #[Test]
    public function extractsArchivePreservingStructureAndStrippingTopLevelDir(): void
    {
        $dload = $this->buildDLoad($this->createRoadRunnerXmlConfig());
        $dload->useMock = true;

        $downloadConfig = new DownloadConfig();
        $downloadConfig->software = 'rr';
        $downloadConfig->type = Type::Archive;
        $downloadConfig->extractPath = (string) $this->destinationDir;

        $dload->addTask($downloadConfig);
        $dload->run();

        // The single wrapping directory (roadrunner-2024.1.5-windows-amd64/) is stripped, so its
        // contents land directly in the destination, keeping their relative layout. Archive mode
        // never renames entries, so the files keep their in-archive names regardless of host OS.
        Assert::true(
            $this->destinationDir->join('rr.exe')->isFile(),
            'Binary should be extracted into the destination root',
        );
        Assert::true($this->destinationDir->join('README.md')->isFile(), 'Sibling files should be extracted too');
        Assert::true($this->destinationDir->join('LICENSE')->isFile(), 'Sibling files should be extracted too');

        // The wrapping version directory must not be recreated inside the destination.
        Assert::false(
            $this->destinationDir->join('roadrunner-2024.1.5-windows-amd64')->exists(),
            'The stripped top-level directory should not be present',
        );
    }

    #[Test]
    public function extractsArchiveWithBinaryAndFileFilterPreservingStructure(): void
    {
        $dload = $this->buildDLoad($this->createNestedToolXmlConfig());
        $dload->useMock = true;
        // A nested layout: pkg-1.0/{bin/app, lib/app/libphp.so, share/app/VERSION.txt}
        $dload->mockArchive = new \SplFileInfo(
            \dirname(__DIR__) . '/Integration/Module/Archive/Fixture/nested.zip',
        );

        $downloadConfig = new DownloadConfig();
        $downloadConfig->software = 'nested';
        $downloadConfig->type = Type::Archive;
        $downloadConfig->extractPath = (string) $this->destinationDir;

        $dload->addTask($downloadConfig);
        $dload->run();

        // The wrapping pkg-1.0/ directory is stripped; the binary and the matched library keep
        // their nested layout, while the unmatched VERSION.txt is filtered out by the <file> rules.
        Assert::true(
            $this->destinationDir->join('bin', 'app')->isFile(),
            'Binary should be extracted keeping its bin/ subdirectory',
        );
        Assert::true(
            $this->destinationDir->join('lib', 'app', 'libphp.so')->isFile(),
            'File matched by a <file> rule should be extracted keeping its subdirectory',
        );
        Assert::false(
            $this->destinationDir->join('share', 'app', 'VERSION.txt')->exists(),
            'Files not matched by any rule should be skipped',
        );
        Assert::false(
            $this->destinationDir->join('pkg-1.0')->exists(),
            'The stripped top-level directory should not be present',
        );
    }

    #[BeforeTest]
    protected function prepare(): void
    {
        // Set up test directory structure
        $projectRoot = Path::create(\dirname(__DIR__, 2));
        $this->testRuntimeDir = $projectRoot->join('runtime', 'tests', 'acceptance');
        $this->tempDir = $this->testRuntimeDir->join('temp');
        $this->destinationDir = $this->testRuntimeDir;

        $this->dload = $this->buildDLoad($this->createTrapXmlConfig());
    }

    #[AfterTest]
    protected function cleanup(): void
    {
        // Clean up test directories
        if ($this->testRuntimeDir->isDir()) {
            $this->removeDirectory($this->testRuntimeDir);
        }
    }

    /**
     * @return non-empty-string
     */
    private function buildDLoad(string $xmlConfig): DLoad
    {
        // The version registry must not leak into the user's cache directory from a test run
        $environment = \getenv() + ['DLOAD_CACHE_DIR' => (string) $this->testRuntimeDir->join('registry')];
        $environment['DLOAD_CACHE_DIR'] = (string) $this->testRuntimeDir->join('registry');

        $container = Bootstrap::init()
            ->withConfig($xmlConfig, [], [], $environment)
            ->finish();
        $container->set($input = new ArgvInput(), InputInterface::class);
        $container->set($output = new BufferedOutput(), OutputInterface::class);
        $container->set(new SymfonyStyle($input, $output), StyleInterface::class);
        $container->set(new Logger($output));

        return $container->get(DLoad::class);
    }

    /**
     * @return non-empty-string
     */
    private function createTomlTestXmlConfig(): string
    {
        return <<<XML
            <?xml version="1.0"?>
            <dload temp-dir="{$this->tempDir}" >
                <registry overwrite="false">
                    <software name="TOML Test" alias="toml-test">
                        <repository type="github" uri="toml-lang/toml-test"
                            asset-pattern="/^toml-test-.*/"
                        />
                        <binary name="toml-test" pattern="/^toml-test-.*/" version-command="version" />
                    </software>
                </registry>
            </dload>
            XML;
    }

    /**
     * @return non-empty-string
     */
    private function createTrapXmlConfig(): string
    {
        return <<<XML
            <?xml version="1.0"?>
            <dload temp-dir="{$this->tempDir}" >
                <registry overwrite="false">
                    <software name="trap">
                        <repository type="github" uri="buggregator/trap"
                            asset-pattern="/^trap.+$/"
                        />
                        <binary name="trap" version-command="--version" />
                    </software>
                </registry>
            </dload>
            XML;

    }

    /**
     * @return non-empty-string
     */
    private function createRoadRunnerXmlConfig(): string
    {
        return <<<XML
            <?xml version="1.0"?>
            <dload temp-dir="{$this->tempDir}" >
                <registry overwrite="false">
                    <software name="RoadRunner" alias="rr">
                        <repository type="github" uri="roadrunner-server/roadrunner"
                            asset-pattern="/^roadrunner-.*/"
                        />
                        <binary name="rr" version-command="--version" />
                    </software>
                </registry>
            </dload>
            XML;
    }

    /**
     * @return non-empty-string
     */
    private function createNestedToolXmlConfig(): string
    {
        return <<<XML
            <?xml version="1.0"?>
            <dload temp-dir="{$this->tempDir}" >
                <registry overwrite="false">
                    <software name="Nested Tool" alias="nested">
                        <repository type="github" uri="example/nested" asset-pattern="/^nested-.*/" />
                        <binary name="app" pattern="/^app$/" />
                        <file pattern="/^libphp\.(so|dylib)$/" />
                    </software>
                </registry>
            </dload>
            XML;
    }

    private function removeDirectory(Path $dir): void
    {
        if (!$dir->isDir()) {
            return;
        }

        $files = \scandir((string) $dir);
        \assert($files !== false);
        foreach ($files as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }

            $path = $dir->join($file);
            if ($path->isDir()) {
                $this->removeDirectory($path);
            } else {
                \unlink((string) $path);
            }
        }

        \rmdir((string) $dir);
    }
}
