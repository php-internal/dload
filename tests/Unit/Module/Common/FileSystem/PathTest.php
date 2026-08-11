<?php

declare(strict_types=1);

namespace Internal\DLoad\Tests\Unit\Module\Common\FileSystem;

use Internal\Path;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Core\Exception\SkipTest;
use Testo\Data\DataProvider;
use Testo\Expect;
use Testo\Test;

#[Covers(Path::class)]
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

    #[Test]
    public function createReturnsPathInstance(): void
    {
        $path = Path::create('test/path');

        Assert::instanceOf($path, Path::class);
    }

    #[Test]
    public function createWithEmptyPathReturnsCurrentDirectory(): void
    {
        $path = Path::create('');

        Assert::same((string) $path, '.');
    }

    #[Test]
    public function createNormalizesDirectorySeparators(): void
    {
        $path = Path::create('test\\path/mixed/separators\\here');

        Assert::same((string) $path, 'test/path/mixed/separators/here');
    }

    #[Test]
    public function createRemovesMultipleSeparators(): void
    {
        $path = Path::create('test//path///extra//separators');

        Assert::same((string) $path, 'test/path/extra/separators');
    }

    #[Test]
    public function createResolvesCurrentDirectorySegments(): void
    {
        $path = Path::create('test/./path/./current');

        Assert::same((string) $path, 'test/path/current');
    }

    #[Test]
    public function createResolvesParentDirectorySegments(): void
    {
        $path = Path::create('test/parent/../path');

        Assert::same((string) $path, 'test/path');
    }

    #[Test]
    public function createThrowsExceptionForInvalidParentNavigation(): void
    {
        Expect::exception(\LogicException::class)->withMessageContaining('Cannot go up from root');

        Path::create('/test/../..');
    }

    #[Test]
    public function joinPathComponents(): void
    {
        $path = Path::create('base/path');

        $result = $path->join('additional', 'components');

        Assert::same((string) $result, 'base/path/additional/components');
    }

    #[Test]
    public function joinWithEmptyComponentsIgnoresThem(): void
    {
        $path = Path::create('base/path');

        $result = $path->join('', 'component', '');

        Assert::same((string) $result, 'base/path/component');
    }

    #[Test]
    public function joinWithPathObjects(): void
    {
        $path = Path::create('base/path');
        $additionalPath = Path::create('additional/path');

        Expect::exception(\LogicException::class)->withMessageContaining('Joining an absolute path is not allowed');

        // Using an absolute Path object which should throw
        $path->join($additionalPath->absolute());
    }

    #[Test]
    public function joinWithRelativePathObjects(): void
    {
        $path = Path::create('base/path');
        $additionalPath = Path::create('additional/path');

        $result = $path->join($additionalPath);

        Assert::same((string) $result, 'base/path/additional/path');
    }

    #[Test]
    public function joinWithAbsolutePathString(): void
    {
        $path = Path::create('base/path');

        Expect::exception(\LogicException::class)->withMessageContaining('Joining an absolute path is not allowed');

        $path->join('/absolute/path');
    }

    #[Test]
    public function name(): void
    {
        $path = Path::create('some/path/file.txt');

        $name = $path->name();

        Assert::same($name, 'file.txt');
    }

    #[Test]
    public function nameWithNoDirectoryComponents(): void
    {
        $path = Path::create('file.txt');

        $name = $path->name();

        Assert::same($name, 'file.txt');
    }

    #[Test]
    public function stem(): void
    {
        $path = Path::create('some/path/file.txt');

        $stem = $path->stem();

        Assert::same($stem, 'file');
    }

    #[Test]
    public function stemWithNoExtension(): void
    {
        $path = Path::create('some/path/file');

        $stem = $path->stem();

        Assert::same($stem, 'file');
    }

    #[Test]
    public function stemWithMultipleDots(): void
    {
        $path = Path::create('some/path/file.config.json');

        $stem = $path->stem();

        Assert::same($stem, 'file.config');
    }

    #[Test]
    public function stemWithHiddenFile(): void
    {
        $path = Path::create('some/path/.hidden');

        $stem = $path->stem();

        Assert::same($stem, '.hidden');
    }

    #[Test]
    public function extension(): void
    {
        $path = Path::create('some/path/file.txt');

        $extension = $path->extension();

        Assert::same($extension, 'txt');
    }

    #[Test]
    public function extensionWithMultipleDots(): void
    {
        $path = Path::create('some/path/file.config.json');

        $extension = $path->extension();

        Assert::same($extension, 'json');
    }

    #[Test]
    public function extensionWithNoExtension(): void
    {
        $path = Path::create('some/path/file');

        $extension = $path->extension();

        Assert::same($extension, '');
    }

    #[Test]
    public function extensionWithHiddenFile(): void
    {
        $path = Path::create('some/path/.hidden');

        $extension = $path->extension();

        Assert::same($extension, 'hidden');
    }

    #[DataProvider('providePathsForParent')]
    #[Test]
    public function parent(string $inputPath, string $expectedParent): void
    {
        $path = Path::create($inputPath);

        $parent = $path->parent();

        Assert::same((string) $parent, $expectedParent);
    }

    #[DataProvider('providePathsForAbsoluteDetection')]
    #[Test]
    public function isAbsolute(string $pathString, bool $expected): void
    {
        $path = Path::create($pathString);

        $isAbsolute = $path->isAbsolute();

        Assert::same($isAbsolute, $expected, "Path '$pathString' should be " . ($expected ? 'absolute' : 'relative'));
    }

    #[Test]
    public function isRelative(): void
    {
        $absolutePath = DIRECTORY_SEPARATOR === '\\'
            ? Path::create('C:/Users/test')
            : Path::create('/home/user');

        $relativePath = Path::create('relative/path');

        Assert::false($absolutePath->isRelative());
        Assert::true($relativePath->isRelative());
    }

    /**
     * This test uses real filesystem access to check if a path exists.
     * It creates a temporary file and checks its existence.
     */
    #[Test]
    public function exists(): void
    {
        $tempFile = \tempnam(\sys_get_temp_dir(), 'path_test_');
        Assert::true(\is_string($tempFile), 'Failed to create temp file');

        $path = Path::create($tempFile);
        $nonExistingPath = Path::create('non/existing/path/file.txt');

        try {
            Assert::true($path->exists());
            Assert::false($nonExistingPath->exists());
        } finally {
            // Clean up
            @\unlink($tempFile);
        }
    }

    /**
     * Note: This test might have limitations depending on the environment.
     * It checks the expected behavior of isDir without requiring an actual directory to exist.
     */
    #[Test]
    public function isDir(): void
    {
        $currentDirPath = Path::create('.');
        $parentDirPath = Path::create('..');
        $filePath = Path::create('file.txt');

        Assert::true($currentDirPath->isDir());
        Assert::true($parentDirPath->isDir());
        Assert::false($filePath->isDir());
    }

    /**
     * Note: This test might have limitations depending on the environment.
     * It checks the expected behavior of isFile without requiring an actual file to exist.
     */
    #[Test]
    public function isFile(): void
    {
        $currentDirPath = Path::create('.');
        $parentDirPath = Path::create('..');
        $filePath = Path::create('file.txt');

        // Create a temporary file to test with
        $tempFile = \tempnam(\sys_get_temp_dir(), 'path_test_');
        Assert::true(\is_string($tempFile), 'Failed to create temp file');
        $realFilePath = Path::create($tempFile);

        try {
            Assert::false($currentDirPath->isFile());
            Assert::false($parentDirPath->isFile());
            Assert::false($filePath->isFile()); // Doesn't exist yet
            Assert::true($realFilePath->isFile(), "Temporary file should be a file `$realFilePath`");
        } finally {
            // Clean up
            @\unlink($tempFile);
        }
    }

    #[Test]
    public function absoluteForAlreadyAbsolutePath(): void
    {
        $absolutePath = DIRECTORY_SEPARATOR === '\\'
            ? Path::create('C:/Users/test')
            : Path::create('/home/user');

        $result = $absolutePath->absolute();

        Assert::same((string) $result, (string) $absolutePath);
    }

    #[Test]
    public function absoluteForRelativePath(): void
    {
        $relativePath = Path::create('relative/path');

        // Skip this test if we can't get cwd
        $cwd = \getcwd();
        if ($cwd === false) {
            throw new SkipTest('Cannot get current working directory');
        }

        $expected = Path::create($cwd . DIRECTORY_SEPARATOR . 'relative/path');

        $result = $relativePath->absolute();

        Assert::same((string) $result, (string) $expected);
    }

    #[Test]
    public function createWindowsTmpFile(): void
    {
        $path = Path::create('C:\Users\roxbl\AppData\Local\Temp\patB6E7.tmp');

        Assert::same((string) $path, 'C:/Users/roxbl/AppData/Local/Temp/patB6E7.tmp');
    }

    #[Test]
    public function toString(): void
    {
        $pathString = 'some/path/file.txt';
        $path = Path::create($pathString);

        $result = (string) $path;

        Assert::same($result, 'some/path/file.txt');
    }
}
