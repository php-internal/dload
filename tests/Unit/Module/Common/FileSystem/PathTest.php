<?php

declare(strict_types=1);

namespace Internal\DLoad\Tests\Unit\Module\Common\FileSystem;

use Internal\Path;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[\Testo\Codecov\Covers(Path::class)]
final class PathTest
{
    public static function providePathsForAbsoluteDetection(): \Generator
    {
        yield 'windows absolute path' => ['C:/Users/test', true];
        yield 'windows drive letter' => ['C:', true];
        yield 'windows relative path' => ['Users/test', false];
        yield 'windows implicit relative' => ['./test', false];
        yield 'unix absolute path' => ['/home/user', true];
        yield 'unix relative path' => ['home/user', false];
        yield 'unix implicit relative' => ['./test', false];
        yield 'dot path' => ['.', false];
        yield 'double dot path' => ['..', false];
    }

    public static function providePathsForParent(): \Generator
    {
        yield ['.', '..'];
        yield ['..', '../..'];
        yield ['path/to/..', '.'];
        yield ['/home', '/.'];
        yield ['C:/Users', 'C:/.'];
        yield ['C:/.', 'C:/.'];
        yield ['filename.txt', '.'];
        yield ['some/path/file.txt', 'some/path'];
    }

    #[\Testo\Test]
    public function testCreateReturnsPathInstance(): void
    {
        // Arrange & Act
        $path = Path::create('test/path');

        // Assert
        \Testo\Assert::instanceOf($path, Path::class);
    }

    #[\Testo\Test]
    public function testCreateWithEmptyPathReturnsCurrentDirectory(): void
    {
        // Arrange & Act
        $path = Path::create('');

        // Assert
        \Testo\Assert::same((string) $path, '.');
    }

    #[\Testo\Test]
    public function testCreateNormalizesDirectorySeparators(): void
    {
        // Arrange & Act
        $path = Path::create('test\\path/mixed/separators\\here');

        // Assert
        \Testo\Assert::same((string) $path, 'test/path/mixed/separators/here');
    }

    #[\Testo\Test]
    public function testCreateRemovesMultipleSeparators(): void
    {
        // Arrange & Act
        $path = Path::create('test//path///extra//separators');

        // Assert
        \Testo\Assert::same((string) $path, 'test/path/extra/separators');
    }

    #[\Testo\Test]
    public function testCreateResolvesCurrentDirectorySegments(): void
    {
        // Arrange & Act
        $path = Path::create('test/./path/./current');

        // Assert
        \Testo\Assert::same((string) $path, 'test/path/current');
    }

    #[\Testo\Test]
    public function testCreateResolvesParentDirectorySegments(): void
    {
        // Arrange & Act
        $path = Path::create('test/parent/../path');

        // Assert
        \Testo\Assert::same((string) $path, 'test/path');
    }

    #[\Testo\Test]
    public function testCreateThrowsExceptionForInvalidParentNavigation(): void
    {
        // Arrange & Assert
        \Testo\Expect::exception(\LogicException::class)->withMessage('Cannot go up from root');

        // Act
        Path::create('/test/../..');
    }

    #[\Testo\Test]
    public function testJoinPathComponents(): void
    {
        // Arrange
        $path = Path::create('base/path');

        // Act
        $result = $path->join('additional', 'components');

        // Assert
        \Testo\Assert::same((string) $result, 'base/path/additional/components');
    }

    #[\Testo\Test]
    public function testJoinWithEmptyComponentsIgnoresThem(): void
    {
        // Arrange
        $path = Path::create('base/path');

        // Act
        $result = $path->join('', 'component', '');

        // Assert
        \Testo\Assert::same((string) $result, 'base/path/component');
    }

    #[\Testo\Test]
    public function testJoinWithPathObjects(): void
    {
        // Arrange
        $path = Path::create('base/path');
        $additionalPath = Path::create('additional/path');

        // Assert (prepare for expected exception)
        \Testo\Expect::exception(\LogicException::class)->withMessage('Joining an absolute path is not allowed');

        // Act
        // Using an absolute Path object which should throw
        $path->join($additionalPath->absolute());
    }

    #[\Testo\Test]
    public function testJoinWithRelativePathObjects(): void
    {
        // Arrange
        $path = Path::create('base/path');
        $additionalPath = Path::create('additional/path');

        // Act
        $result = $path->join($additionalPath);

        // Assert
        \Testo\Assert::same((string) $result, 'base/path/additional/path');
    }

    #[\Testo\Test]
    public function testJoinWithAbsolutePathString(): void
    {
        // Arrange
        $path = Path::create('base/path');

        // Assert (prepare for expected exception)
        \Testo\Expect::exception(\LogicException::class)->withMessage('Joining an absolute path is not allowed');

        // Act
        $path->join('/absolute/path');
    }

    #[\Testo\Test]
    public function testName(): void
    {
        // Arrange
        $path = Path::create('some/path/file.txt');

        // Act
        $name = $path->name();

        // Assert
        \Testo\Assert::same($name, 'file.txt');
    }

    #[\Testo\Test]
    public function testNameWithNoDirectoryComponents(): void
    {
        // Arrange
        $path = Path::create('file.txt');

        // Act
        $name = $path->name();

        // Assert
        \Testo\Assert::same($name, 'file.txt');
    }

    #[\Testo\Test]
    public function testStem(): void
    {
        // Arrange
        $path = Path::create('some/path/file.txt');

        // Act
        $stem = $path->stem();

        // Assert
        \Testo\Assert::same($stem, 'file');
    }

    #[\Testo\Test]
    public function testStemWithNoExtension(): void
    {
        // Arrange
        $path = Path::create('some/path/file');

        // Act
        $stem = $path->stem();

        // Assert
        \Testo\Assert::same($stem, 'file');
    }

    #[\Testo\Test]
    public function testStemWithMultipleDots(): void
    {
        // Arrange
        $path = Path::create('some/path/file.config.json');

        // Act
        $stem = $path->stem();

        // Assert
        \Testo\Assert::same($stem, 'file.config');
    }

    #[\Testo\Test]
    public function testStemWithHiddenFile(): void
    {
        // Arrange
        $path = Path::create('some/path/.hidden');

        // Act
        $stem = $path->stem();

        // Assert
        \Testo\Assert::same($stem, '.hidden');
    }

    #[\Testo\Test]
    public function testExtension(): void
    {
        // Arrange
        $path = Path::create('some/path/file.txt');

        // Act
        $extension = $path->extension();

        // Assert
        \Testo\Assert::same($extension, 'txt');
    }

    #[\Testo\Test]
    public function testExtensionWithMultipleDots(): void
    {
        // Arrange
        $path = Path::create('some/path/file.config.json');

        // Act
        $extension = $path->extension();

        // Assert
        \Testo\Assert::same($extension, 'json');
    }

    #[\Testo\Test]
    public function testExtensionWithNoExtension(): void
    {
        // Arrange
        $path = Path::create('some/path/file');

        // Act
        $extension = $path->extension();

        // Assert
        \Testo\Assert::same($extension, '');
    }

    #[\Testo\Test]
    public function testExtensionWithHiddenFile(): void
    {
        // Arrange
        $path = Path::create('some/path/.hidden');

        // Act
        $extension = $path->extension();

        // Assert
        \Testo\Assert::same($extension, 'hidden');
    }

    #[\Testo\Data\DataProvider('providePathsForParent')]
    #[\Testo\Test]
    public function testParent(string $inputPath, string $expectedParent): void
    {
        // Arrange
        $path = Path::create($inputPath);

        // Act
        $parent = $path->parent();

        // Assert
        \Testo\Assert::same((string) $parent, $expectedParent);
    }

    #[\Testo\Data\DataProvider('providePathsForAbsoluteDetection')]
    #[\Testo\Test]
    public function testIsAbsolute(string $pathString, bool $expected): void
    {
        // Arrange
        $path = Path::create($pathString);

        // Act
        $isAbsolute = $path->isAbsolute();

        // Assert
        \Testo\Assert::same($isAbsolute, $expected, "Path '$pathString' should be " . ($expected ? 'absolute' : 'relative'));
    }

    #[\Testo\Test]
    public function testIsRelative(): void
    {
        // Arrange
        $absolutePath = DIRECTORY_SEPARATOR === '\\'
            ? Path::create('C:/Users/test')
            : Path::create('/home/user');

        $relativePath = Path::create('relative/path');

        // Act & Assert
        \Testo\Assert::false($absolutePath->isRelative());
        \Testo\Assert::true($relativePath->isRelative());
    }

    /**
     * This test uses real filesystem access to check if a path exists.
     * It creates a temporary file and checks its existence.
     */
    #[\Testo\Test]
    public function testExists(): void
    {
        // Arrange
        $tempFile = \tempnam(\sys_get_temp_dir(), 'path_test_');
        self::assertIsString($tempFile, 'Failed to create temp file');

        $path = Path::create($tempFile);
        $nonExistingPath = Path::create('non/existing/path/file.txt');

        // Act & Assert
        try {
            \Testo\Assert::true($path->exists());
            \Testo\Assert::false($nonExistingPath->exists());
        } finally {
            // Clean up
            @\unlink($tempFile);
        }
    }

    /**
     * Note: This test might have limitations depending on the environment.
     * It checks the expected behavior of isDir without requiring an actual directory to exist.
     */
    #[\Testo\Test]
    public function testIsDir(): void
    {
        // Arrange
        $currentDirPath = Path::create('.');
        $parentDirPath = Path::create('..');
        $filePath = Path::create('file.txt');

        // Act & Assert
        \Testo\Assert::true($currentDirPath->isDir());
        \Testo\Assert::true($parentDirPath->isDir());
        \Testo\Assert::false($filePath->isDir());
    }

    /**
     * Note: This test might have limitations depending on the environment.
     * It checks the expected behavior of isFile without requiring an actual file to exist.
     */
    #[\Testo\Test]
    public function testIsFile(): void
    {
        // Arrange
        $currentDirPath = Path::create('.');
        $parentDirPath = Path::create('..');
        $filePath = Path::create('file.txt');

        // Create a temporary file to test with
        $tempFile = \tempnam(\sys_get_temp_dir(), 'path_test_');
        self::assertIsString($tempFile, 'Failed to create temp file');
        $realFilePath = Path::create($tempFile);

        // Act & Assert
        try {
            \Testo\Assert::false($currentDirPath->isFile());
            \Testo\Assert::false($parentDirPath->isFile());
            \Testo\Assert::false($filePath->isFile()); // Doesn't exist yet
            \Testo\Assert::true($realFilePath->isFile(), "Temporary file should be a file `$realFilePath`");
        } finally {
            // Clean up
            @\unlink($tempFile);
        }
    }

    #[\Testo\Test]
    public function testAbsoluteForAlreadyAbsolutePath(): void
    {
        // Arrange
        $absolutePath = DIRECTORY_SEPARATOR === '\\'
            ? Path::create('C:/Users/test')
            : Path::create('/home/user');

        // Act
        $result = $absolutePath->absolute();

        // Assert
        \Testo\Assert::same((string) $result, (string) $absolutePath);
    }

    #[\Testo\Test]
    public function testAbsoluteForRelativePath(): void
    {
        // Arrange
        $relativePath = Path::create('relative/path');

        // Skip this test if we can't get cwd
        $cwd = \getcwd();
        if ($cwd === false) {
            throw new \Testo\Core\Exception\SkipTest('Cannot get current working directory');
        }

        $expected = Path::create($cwd . DIRECTORY_SEPARATOR . 'relative/path');

        // Act
        $result = $relativePath->absolute();

        // Assert
        \Testo\Assert::same((string) $result, (string) $expected);
    }

    #[\Testo\Test]
    public function testCreateWindowsTmpFile(): void
    {
        $path = Path::create('C:\Users\roxbl\AppData\Local\Temp\patB6E7.tmp');

        \Testo\Assert::same((string) $path, 'C:/Users/roxbl/AppData/Local/Temp/patB6E7.tmp');
    }

    #[\Testo\Test]
    public function testToString(): void
    {
        // Arrange
        $pathString = 'some/path/file.txt';
        $path = Path::create($pathString);

        // Act
        $result = (string) $path;

        // Assert
        \Testo\Assert::same($result, 'some/path/file.txt');
    }
}
