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
    private string $tempDir;
    private ArchiveFactory $factory;

    public static function provideArchiveTypes(): \Generator
    {
        yield 'zip' => ['zip', ZipPharArchive::class];
        yield 'tar.gz' => ['tar.gz', TarPharArchive::class];
        yield 'phar' => ['phar', PharArchive::class];
        yield 'exe' => ['exe', NullArchive::class];
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
