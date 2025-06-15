<?php

declare(strict_types=1);

namespace Internal\DLoad\Tests\Acceptance;

use Internal\DLoad\Bootstrap;
use Internal\DLoad\DLoad;
use Internal\DLoad\Module\Common\Config\Action\Download as DownloadConfig;
use Internal\DLoad\Module\Common\Config\Action\Type;
use Internal\DLoad\Module\Common\FileSystem\Path;
use Internal\DLoad\Module\Common\OperatingSystem;
use Internal\DLoad\Service\Logger;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\StyleInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Acceptance tests for DLoad class.
 *
 * Tests the complete download workflow using real repositories and file extraction.
 * Requires internet connectivity to download actual software packages.
 */
#[CoversClass(DLoad::class)]
final class DLoadTest extends TestCase
{
    private Path $testRuntimeDir;
    private Path $tempDir;
    private Path $destinationDir;
    private DLoad $dload;

    public function testDownloadsTrapPharSuccessfully(): void
    {
        // Arrange
        $downloadConfig = new DownloadConfig();
        $downloadConfig->software = 'trap';
        $downloadConfig->version = '1.13.16';
        $downloadConfig->type = Type::Phar;
        $downloadConfig->extractPath = (string) $this->destinationDir;

        // Act
        $this->dload->addTask($downloadConfig);
        $this->dload->run();

        // Assert - Check that trap.phar was downloaded
        $expectedPharPath = (string) $this->destinationDir->join('trap.phar');
        self::assertFileExists($expectedPharPath, 'Trap PHAR should be downloaded to destination directory');

        // Verify the file is not empty
        self::assertGreaterThan(1024, \filesize($expectedPharPath), 'Downloaded PHAR should have substantial size');

        // Verify file permissions (should be executable)
        if (PHP_OS_FAMILY !== 'Windows') {
            self::assertTrue(\is_executable($expectedPharPath), 'PHAR file should be executable');
        }
    }

    public function testDownloadsTrapBinary(): void
    {
        // Arrange
        $downloadConfig = new DownloadConfig();
        $downloadConfig->software = 'trap';
        $downloadConfig->version = '1.13.16';
        $downloadConfig->type = Type::Binary;
        $downloadConfig->extractPath = (string) $this->destinationDir;

        // Act
        $this->dload->addTask($downloadConfig);
        $this->dload->run();

        // Assert - Check that Trap binary was downloaded and extracted
        $os = OperatingSystem::fromGlobals();
        $expectedPharPath = (string) $this->destinationDir->join('trap' . $os->getBinaryExtension());
        self::assertFileExists($expectedPharPath, 'Trap binary should be downloaded to destination directory');

        // Verify the file is not empty
        self::assertGreaterThan(1024, \filesize($expectedPharPath), 'Downloaded binary should have substantial size');

        self::assertTrue(\is_executable($expectedPharPath), 'Binary file should be executable');
    }

    protected function setUp(): void
    {
        // Set up test directory structure
        $projectRoot = Path::create(\dirname(__DIR__, 2));
        $this->testRuntimeDir = $projectRoot->join('runtime', 'tests', 'acceptance');
        $this->tempDir = $this->testRuntimeDir->join('temp');
        $this->destinationDir = $this->testRuntimeDir;

        // Initialize DLoad through Bootstrap
        $container = Bootstrap::init()
            ->withConfig(
                $this->createTrapXmlConfig(),
                [],
                [],
                \getenv(),
            )
            ->finish();
        $container->set($input = new ArgvInput(), InputInterface::class);
        $container->set($output = new BufferedOutput(), OutputInterface::class);
        $container->set(new SymfonyStyle($input, $output), StyleInterface::class);
        $container->set(new Logger($output));

        $this->dload = $container->get(DLoad::class);
    }

    protected function tearDown(): void
    {
        // Clean up test directories
        if ($this->testRuntimeDir->isDir()) {
            $this->removeDirectory($this->testRuntimeDir);
        }
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
