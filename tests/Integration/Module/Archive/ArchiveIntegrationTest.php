<?php

declare(strict_types=1);

namespace Internal\DLoad\Tests\Integration\Module\Archive;

use Internal\DLoad\Module\Archive\Archive;
use Internal\DLoad\Module\Archive\ArchiveFactory;
use Internal\DLoad\Module\Archive\Internal\NullArchive;
use Internal\DLoad\Module\Archive\Internal\PharArchive;
use Internal\DLoad\Module\Archive\Internal\TarPharArchive;
use Internal\DLoad\Module\Archive\Internal\ZipPharArchive;
use Testo\Assert;
use Testo\Core\Exception\SkipTest;
use Testo\Data\DataProvider;
use Testo\Filter\Group;
use Testo\Lifecycle\AfterTest;
use Testo\Lifecycle\BeforeTest;
use Testo\Test;

/**
 * Integration tests for Archive module
 *
 * These tests verify that the Archive module components work together correctly.
 * They require the phar extension to be enabled and temporary files to be created.
 */
#[Group('integration')]
final class ArchiveIntegrationTest
{
    /**
     * Files of a nested archive layout, mimicking a self-contained tool
     * (a binary that resolves a shared library through a relative path).
     */
    private const NESTED_LAYOUT = [
        'pkg-1.0/bin/app' => "binary\n",
        'pkg-1.0/lib/app/libphp.so' => "shared library\n",
        'pkg-1.0/share/app/VERSION.txt' => "1.0\n",
    ];

    private string $tempDir;
    private ArchiveFactory $factory;

    public static function provideArchiveTypes(): \Generator
    {
        yield 'zip' => ['zip', ZipPharArchive::class];
        yield 'tar.gz' => ['tar.gz', TarPharArchive::class];
        yield 'phar' => ['phar', PharArchive::class];
        yield 'exe' => ['exe', NullArchive::class];
    }

    public static function provideNestedArchiveTypes(): \Generator
    {
        yield 'zip' => ['zip'];
        yield 'tar.gz' => ['tar.gz'];
    }

    #[DataProvider('provideArchiveTypes')]
    #[Test]
    public function factoryCreateReturnsCorrectImplementation(
        string $extension,
        string $className,
    ): void {
        // Skip if we can't verify the implementation type
        if (!\class_exists($className)) {
            throw new SkipTest("Class $className not available");
        }

        $file = \Mockery::mock(\SplFileInfo::class);
        $file->allows('getFilename')->andReturn('test.' . $extension);
        $file->allows('isFile')->andReturn(true);
        $file->allows('isReadable')->andReturn(true);

        $archive = $this->factory->create($file);

        Assert::instanceOf($archive, $className);
    }

    #[Test]
    public function factoryExtendWithCustomImplementation(): void
    {
        $customArchive = \Mockery::mock(Archive::class);

        // Register custom implementation for .custom extension
        $this->factory->extend(
            static fn(\SplFileInfo $file) =>
                \str_ends_with($file->getFilename(), '.custom') ? $customArchive : null,
            ['custom'],
        );

        $file = \Mockery::mock(\SplFileInfo::class);
        $file->allows('getFilename')->andReturn('test.custom');
        $file->allows('isFile')->andReturn(true);
        $file->allows('isReadable')->andReturn(true);

        $archive = $this->factory->create($file);

        Assert::same($archive, $customArchive);
    }

    #[DataProvider('provideNestedArchiveTypes')]
    #[Test]
    public function extractKeysEntriesByTheirArchiveRelativePath(string $type): void
    {
        $archive = $this->factory->create(new \SplFileInfo($this->createNestedArchive($type)));

        $keys = [];
        foreach ($archive->extract() as $relativePath => $_) {
            $keys[] = $relativePath;
        }

        \sort($keys);
        Assert::same($keys, \array_keys(self::NESTED_LAYOUT));
    }

    #[DataProvider('provideNestedArchiveTypes')]
    #[Test]
    public function extractPreservesTheNestedDirectoryStructure(string $type): void
    {
        $archive = $this->factory->create(new \SplFileInfo($this->createNestedArchive($type)));
        $target = $this->tempDir . '/extracted';

        $extractor = $archive->extract();
        while ($extractor->valid()) {
            $relativePath = $extractor->key();
            $destination = $target . '/' . $relativePath;
            \is_dir(\dirname($destination)) or \mkdir(\dirname($destination), 0777, true);
            // `send()` extracts the current entry and advances the generator on its own.
            $extractor->send(new \SplFileInfo($destination));
        }

        foreach (self::NESTED_LAYOUT as $relativePath => $content) {
            $path = $target . '/' . $relativePath;
            Assert::true(\is_file($path), "Entry `{$relativePath}` should be extracted preserving its path");
            Assert::same(\file_get_contents($path), $content);
        }
    }

    #[BeforeTest]
    protected function prepare(): void
    {
        // Skip tests if phar extension is not available
        if (!\class_exists(\PharData::class)) {
            throw new SkipTest('Phar extension is not available');
        }

        // Create temporary directory for test files in project runtime
        $projectRoot = \dirname(__DIR__, 4); // Four levels up from this file
        $this->tempDir = $projectRoot . '/runtime/tests/archive-integration-' . \uniqid();
        \mkdir($this->tempDir, 0777, true);

        // Create factory
        $this->factory = new ArchiveFactory();
    }

    #[AfterTest]
    protected function cleanup(): void
    {
        // Clean up temporary directory
        if (\is_dir($this->tempDir)) {
            $this->removeDirectory($this->tempDir);
        }
    }

    /**
     * Builds a real archive of the given type with a nested directory layout.
     *
     * @param non-empty-string $type Either `zip` or `tar.gz`
     * @return non-empty-string Path to the created archive
     */
    private function createNestedArchive(string $type): string
    {
        if ($type === 'zip') {
            if (!\class_exists(\ZipArchive::class)) {
                throw new SkipTest('Zip extension is not available');
            }

            $path = $this->tempDir . '/nested.zip';
            $zip = new \ZipArchive();
            $zip->open($path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
            foreach (self::NESTED_LAYOUT as $entry => $content) {
                $zip->addFromString($entry, $content);
            }
            $zip->close();

            return $path;
        }

        // Build the tar from real on-disk files: `PharData::addFromString()` on a tar can produce
        // entries that read back empty once the archive is gzip-compressed and reopened on Linux.
        $sourceDir = $this->tempDir . '/nested-src';
        foreach (self::NESTED_LAYOUT as $entry => $content) {
            $file = $sourceDir . '/' . $entry;
            \is_dir(\dirname($file)) or \mkdir(\dirname($file), 0777, true);
            \file_put_contents($file, $content);
        }

        $tarPath = $this->tempDir . '/nested.tar';
        $phar = new \PharData($tarPath);
        $phar->buildFromDirectory($sourceDir);
        $phar->compress(\Phar::GZ);
        unset($phar);
        // Drop the intermediate uncompressed tar so only the .tar.gz remains.
        \is_file($tarPath) and \unlink($tarPath);

        return $tarPath . '.gz';
    }

    /**
     * Recursively remove a directory and its contents
     */
    private function removeDirectory(string $dir): void
    {
        if (!\is_dir($dir)) {
            return;
        }

        $items = \scandir($dir);
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $dir . '/' . $item;
            if (\is_dir($path)) {
                $this->removeDirectory($path);
            } else {
                \unlink($path);
            }
        }

        \rmdir($dir);
    }
}
