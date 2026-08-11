<?php

declare(strict_types=1);

namespace Internal\DLoad\Tests\Unit\Module\Archive\API;

use Internal\DLoad\Module\Archive\Archive;
use Internal\DLoad\Module\Archive\ArchiveFactory;
use Internal\DLoad\Module\Archive\Internal\NullArchive;
use Internal\DLoad\Module\Archive\Internal\PharArchive;
use Internal\DLoad\Module\Archive\Internal\TarPharArchive;
use Internal\DLoad\Module\Archive\Internal\ZipPharArchive;
use Internal\DLoad\Tests\Unit\Module\Archive\Stub\ArchiveFixtureGenerator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[\Testo\Codecov\Covers(ArchiveFactory::class)]
final class ArchiveFactoryTest
{
    private ArchiveFactory $factory;
    private static string $fixturesDir;
    private static ?ArchiveFixtureGenerator $fixtureGenerator = null;
    private static array $archiveFixtures = [];

    public static function provideDefaultSupportedExtensions(): \Generator
    {
        yield 'zip extension' => ['zip'];
        yield 'tar.gz extension' => ['tar.gz'];
        yield 'phar extension' => ['phar'];
    }

    public static function provideArchiveFiles(): \Generator
    {
        yield 'zip file' => ['archive.zip', ZipPharArchive::class];
        yield 'tar.gz file' => ['archive.tar.gz', TarPharArchive::class];
        yield 'phar file' => ['archive.phar', PharArchive::class];
    }

    #[\Testo\Data\DataProvider('provideDefaultSupportedExtensions')]
    #[\Testo\Test]
    public function testGetSupportedExtensionsReturnsDefaultExtensions(string $extension): void
    {
        // Act
        $extensions = $this->factory->getSupportedExtensions();

        // Assert
        \Testo\Assert::contains($extensions, $extension);
    }

    #[\Testo\Data\DataProvider('provideArchiveFiles')]
    #[\Testo\Test]
    public function testCreateReturnsCorrectArchiveTypeForExtension(
        string $filename,
        string $expectedClass,
    ): void {
        // Skip test if the fixture wasn't created
        $extension = \pathinfo($filename, PATHINFO_EXTENSION);
        if ($extension === 'gz') {
            $extension = 'tar.gz';
        }

        if (!isset(self::$archiveFixtures[$extension])) {
            throw new \Testo\Core\Exception\SkipTest("Archive fixture for {$extension} could not be created");
        }

        // Arrange - use actual file
        $filePath = self::$archiveFixtures[$extension];
        $file = new \SplFileInfo($filePath);

        // Act
        $archive = $this->factory->create($file);

        // Assert
        \Testo\Assert::instanceOf($archive, $expectedClass);
    }

    #[\Testo\Test]
    public function testExtendAddsCustomMatcher(): void
    {
        // Arrange
        $mockArchive = $this->createMock(Archive::class);
        $customExtension = 'custom';

        $this->factory->extend(
            static fn(\SplFileInfo $file) =>
                \str_ends_with($file->getFilename(), '.custom') ? $mockArchive : null,
            [$customExtension],
        );

        $file = $this->createFileInfoMock('test.custom');

        // Act
        $result = $this->factory->create($file);
        $extensions = $this->factory->getSupportedExtensions();

        // Assert
        \Testo\Assert::same($result, $mockArchive);
        \Testo\Assert::contains($extensions, $customExtension);
    }

    #[\Testo\Test]
    public function testCreateReturnsNullArchiveForNonArchiveFile(): void
    {
        // Arrange
        $file = $this->createFileInfoMock('binary-executable');

        // Act
        $archive = $this->factory->create($file);

        // Assert
        \Testo\Assert::instanceOf($archive, NullArchive::class);
    }

    #[\Testo\Test]
    public function testCreateThrowsExceptionForInvalidFile(): void
    {
        // Arrange
        $file = $this->createMock(\SplFileInfo::class);
        $file->method('getFilename')->willReturn('invalid-file');
        $file->method('isFile')->willReturn(false);

        // Assert
        \Testo\Expect::exception(\InvalidArgumentException::class);

        // Act
        $this->factory->create($file);
    }

    #[\Testo\Test]
    public function testExtendPrioritizesNewMatchersOverExisting(): void
    {
        // Arrange
        $mockArchive = $this->createMock(Archive::class);
        $zipFile = $this->createFileInfoMock('test.zip');

        // Override the default zip handler
        $this->factory->extend(
            static fn(\SplFileInfo $file) =>
                \str_ends_with($file->getFilename(), '.zip') ? $mockArchive : null,
            [],
        );

        // Act
        $result = $this->factory->create($zipFile);

        // Assert
        \Testo\Assert::same($result, $mockArchive);
    }

    #[\Testo\Test]
    public function testNullArchiveUsedAsLastResort(): void
    {
        // Arrange - create custom matcher that always returns null
        $this->factory->extend(
            static fn(\SplFileInfo $file) => null,
            [],
        );

        $file = $this->createFileInfoMock('unknown-file-type');

        // Act
        $archive = $this->factory->create($file);

        // Assert - should fall back to NullArchive
        \Testo\Assert::instanceOf($archive, NullArchive::class);
    }

    #[\Testo\Lifecycle\BeforeClass]
    public static function setUpBeforeClass(): void
    {
        // Define project's test runtime directory
        $projectRoot = \dirname(__DIR__, 5); // Five levels up from this file
        self::$fixturesDir = $projectRoot . '/runtime/tests/archive-fixtures';

        // Create archive fixtures
        self::$fixtureGenerator = new ArchiveFixtureGenerator(self::$fixturesDir);
        self::$archiveFixtures = self::$fixtureGenerator->generateArchives();
    }

    #[\Testo\Lifecycle\AfterClass]
    public static function tearDownAfterClass(): void
    {
        // Clean up fixtures
        if (self::$fixtureGenerator !== null) {
            self::$fixtureGenerator->cleanup();
        }
    }

    #[\Testo\Lifecycle\BeforeTest]
    protected function setUp(): void
    {
        // Arrange
        $this->factory = new ArchiveFactory();
    }

    /**
     * Creates a mock SplFileInfo that returns the given filename
     * and is configured as a valid, readable file
     */
    private function createFileInfoMock(string $filename): \SplFileInfo
    {
        $file = $this->createMock(\SplFileInfo::class);
        $file->method('getFilename')->willReturn($filename);
        $file->method('isFile')->willReturn(true);
        $file->method('isReadable')->willReturn(true);
        $file->method('getPathname')->willReturn('/path/to/' . $filename);

        return $file;
    }
}
