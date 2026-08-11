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
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Core\Exception\SkipTest;
use Testo\Data\DataProvider;
use Testo\Expect;
use Testo\Lifecycle\AfterClass;
use Testo\Lifecycle\BeforeClass;
use Testo\Lifecycle\BeforeTest;
use Testo\Test;

#[Covers(ArchiveFactory::class)]
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

    #[BeforeClass]
    public static function prepareClass(): void
    {
        // Define project's test runtime directory
        $projectRoot = \dirname(__DIR__, 5); // Five levels up from this file
        self::$fixturesDir = $projectRoot . '/runtime/tests/archive-fixtures';

        // Create archive fixtures
        self::$fixtureGenerator = new ArchiveFixtureGenerator(self::$fixturesDir);
        self::$archiveFixtures = self::$fixtureGenerator->generateArchives();
    }

    #[AfterClass]
    public static function cleanupClass(): void
    {
        // Clean up fixtures
        if (self::$fixtureGenerator !== null) {
            self::$fixtureGenerator->cleanup();
        }
    }

    #[DataProvider('provideDefaultSupportedExtensions')]
    #[Test]
    public function getSupportedExtensionsReturnsDefaultExtensions(string $extension): void
    {
        $extensions = $this->factory->getSupportedExtensions();

        Assert::contains($extensions, $extension);
    }

    #[DataProvider('provideArchiveFiles')]
    #[Test]
    public function createReturnsCorrectArchiveTypeForExtension(
        string $filename,
        string $expectedClass,
    ): void {
        // Skip test if the fixture wasn't created
        $extension = \pathinfo($filename, PATHINFO_EXTENSION);
        if ($extension === 'gz') {
            $extension = 'tar.gz';
        }

        if (!isset(self::$archiveFixtures[$extension])) {
            throw new SkipTest("Archive fixture for {$extension} could not be created");
        }

        // Arrange - use actual file
        $filePath = self::$archiveFixtures[$extension];
        $file = new \SplFileInfo($filePath);

        $archive = $this->factory->create($file);

        Assert::instanceOf($archive, $expectedClass);
    }

    #[Test]
    public function extendAddsCustomMatcher(): void
    {
        $mockArchive = \Mockery::mock(Archive::class);
        $customExtension = 'custom';

        $this->factory->extend(
            static fn(\SplFileInfo $file) =>
                \str_ends_with($file->getFilename(), '.custom') ? $mockArchive : null,
            [$customExtension],
        );

        $file = $this->createFileInfoMock('test.custom');

        $result = $this->factory->create($file);
        $extensions = $this->factory->getSupportedExtensions();

        Assert::same($result, $mockArchive);
        Assert::contains($extensions, $customExtension);
    }

    #[Test]
    public function createReturnsNullArchiveForNonArchiveFile(): void
    {
        $file = $this->createFileInfoMock('binary-executable');

        $archive = $this->factory->create($file);

        Assert::instanceOf($archive, NullArchive::class);
    }

    #[Test]
    public function createThrowsExceptionForInvalidFile(): void
    {
        $file = \Mockery::mock(\SplFileInfo::class);
        $file->allows('getFilename')->andReturn('invalid-file');
        $file->allows('isFile')->andReturn(false);

        Expect::exception(\InvalidArgumentException::class);

        $this->factory->create($file);
    }

    #[Test]
    public function extendPrioritizesNewMatchersOverExisting(): void
    {
        $mockArchive = \Mockery::mock(Archive::class);
        $zipFile = $this->createFileInfoMock('test.zip');

        // Override the default zip handler
        $this->factory->extend(
            static fn(\SplFileInfo $file) =>
                \str_ends_with($file->getFilename(), '.zip') ? $mockArchive : null,
            [],
        );

        $result = $this->factory->create($zipFile);

        Assert::same($result, $mockArchive);
    }

    #[Test]
    public function nullArchiveUsedAsLastResort(): void
    {
        // Arrange - create custom matcher that always returns null
        $this->factory->extend(
            static fn(\SplFileInfo $file) => null,
            [],
        );

        $file = $this->createFileInfoMock('unknown-file-type');

        $archive = $this->factory->create($file);

        // Assert - should fall back to NullArchive
        Assert::instanceOf($archive, NullArchive::class);
    }

    #[BeforeTest]
    protected function prepare(): void
    {
        $this->factory = new ArchiveFactory();
    }

    /**
     * Creates a mock SplFileInfo that returns the given filename
     * and is configured as a valid, readable file
     */
    private function createFileInfoMock(string $filename): \SplFileInfo
    {
        $file = \Mockery::mock(\SplFileInfo::class);
        $file->allows('getFilename')->andReturn($filename);
        $file->allows('isFile')->andReturn(true);
        $file->allows('isReadable')->andReturn(true);
        $file->allows('getPathname')->andReturn('/path/to/' . $filename);

        return $file;
    }
}
