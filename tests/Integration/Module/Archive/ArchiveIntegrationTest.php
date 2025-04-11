<?php

declare(strict_types=1);

namespace Internal\DLoad\Tests\Integration\Module\Archive;

use Internal\DLoad\Module\Archive\ArchiveFactory;
use Internal\DLoad\Module\Archive\Internal\NullArchive;
use Internal\DLoad\Module\Archive\Internal\PharArchive;
use Internal\DLoad\Module\Archive\Internal\TarPharArchive;
use Internal\DLoad\Module\Archive\Internal\ZipPharArchive;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Integration tests for Archive module
 *
 * These tests verify that the Archive module components work together correctly.
 * They require the phar extension to be enabled and temporary files to be created.
 */
#[Group('integration')]
final class ArchiveIntegrationTest extends TestCase
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
    public function testFactoryCreateReturnsCorrectImplementation(
        string $extension,
        string $className,
    ): void {
        // Skip if we can't verify the implementation type
        if (!\class_exists($className)) {
            self::markTestSkipped("Class $className not available");
        }

        // Arrange - create mock file with extension
        $file = $this->createMock(\SplFileInfo::class);
        $file->method('getFilename')->willReturn('test.' . $extension);
        $file->method('isFile')->willReturn(true);
        $file->method('isReadable')->willReturn(true);

        // Act - create archive handler
        $archive = $this->factory->create($file);

        // Assert - check implementation type
        self::assertInstanceOf($className, $archive);
    }

    public function testFactoryExtendWithCustomImplementation(): void
    {
        // Arrange - create custom archive mock
        $customArchive = $this->createMock('Internal\DLoad\Module\Archive\Archive');

        // Register custom implementation for .custom extension
        $this->factory->extend(
            static fn(\SplFileInfo $file) =>
                \str_ends_with($file->getFilename(), '.custom') ? $customArchive : null,
            ['custom'],
        );

        // Create mock file with custom extension
        $file = $this->createMock(\SplFileInfo::class);
        $file->method('getFilename')->willReturn('test.custom');
        $file->method('isFile')->willReturn(true);
        $file->method('isReadable')->willReturn(true);

        // Act
        $archive = $this->factory->create($file);

        // Assert
        self::assertSame($customArchive, $archive);
    }

    protected function setUp(): void
    {
        // Skip tests if phar extension is not available
        if (!\class_exists(\PharData::class)) {
            self::markTestSkipped('Phar extension is not available');
        }

        // Create temporary directory for test files in project runtime
        $projectRoot = \dirname(__DIR__, 4); // Four levels up from this file
        $this->tempDir = $projectRoot . '/runtime/tests/archive-integration-' . \uniqid();
        \mkdir($this->tempDir, 0777, true);

        // Create factory
        $this->factory = new ArchiveFactory();
    }

    protected function tearDown(): void
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
