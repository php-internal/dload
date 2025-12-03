<?php

declare(strict_types=1);

namespace Internal\DLoad\Tests\Unit\Module\Velox\Internal\Config\Pipeline\Processor;

use Internal\DLoad\Module\Config\Schema\Action\Velox as VeloxAction;
use Internal\DLoad\Module\Velox\Exception\Config as ConfigException;
use Internal\DLoad\Module\Velox\Internal\Config\Pipeline\ConfigContext;
use Internal\DLoad\Module\Velox\Internal\Config\Pipeline\Processor\LocalFileProcessor;
use Internal\DLoad\Module\Velox\Internal\Config\Pipeline\TomlData;
use Internal\Path;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(LocalFileProcessor::class)]
final class LocalFileProcessorTest extends TestCase
{
    private LocalFileProcessor $processor;
    private VeloxAction $veloxAction;
    private Path $buildDir;

    public static function provideValidTomlFiles(): \Generator
    {
        yield 'simple key-value' => [
            'binary_name = "test-binary"',
            ['binary_name' => 'test-binary'],
        ];

        yield 'section with nested values' => [
            '[roadrunner]' . "\n" . 'version = "latest"',
            ['roadrunner' => null], // Just verify the key exists
        ];

        yield 'empty file' => [
            '',
            ['_empty' => null], // Add assertion to avoid risky test
        ];

        yield 'file with comments' => [
            '# This is a comment' . "\n" . 'key = "value"',
            ['key' => 'value'],
        ];

        yield 'complex nested structure' => [
            '[github.plugins.http]' . "\n" . 'ref = "v4.7.0"' . "\n" .
            '[github.plugins.logger]' . "\n" . 'ref = "v1.2.3"',
            ['github' => null],
        ];
    }

    public function testInvokeReturnsOriginalContextWhenConfigFileIsNull(): void
    {
        // Arrange
        $this->veloxAction->configFile = null;
        $originalContext = new ConfigContext(
            $this->veloxAction,
            $this->buildDir,
            new TomlData(['base' => 'data']),
            ['initial' => 'metadata'],
        );

        // Act
        $result = $this->processor->__invoke($originalContext);

        // Assert
        self::assertSame($originalContext, $result);
    }

    public function testInvokeThrowsExceptionWhenConfigFileDoesNotExist(): void
    {
        // Arrange
        $configPath = '/non/existent/path.toml';
        $this->veloxAction->configFile = $configPath;
        $context = new ConfigContext(
            $this->veloxAction,
            $this->buildDir,
            new TomlData(),
            [],
        );

        // Assert (before Act for exceptions)
        $this->expectException(ConfigException::class);
        $this->expectExceptionMessage("Local config file not found: {$configPath}");

        // Act
        $this->processor->__invoke($context);
    }

    public function testInvokeThrowsExceptionWhenFileCannotBeRead(): void
    {
        // Skip this test on Windows as chmod doesn't work the same way
        if (PHP_OS_FAMILY === 'Windows') {
            self::markTestSkipped('File permission tests are not reliable on Windows');
        }

        // Arrange
        $tempFile = $this->createTempFile('test content');
        $this->veloxAction->configFile = $tempFile;
        $context = new ConfigContext(
            $this->veloxAction,
            $this->buildDir,
            new TomlData(),
            [],
        );

        // Make file unreadable by changing permissions
        \chmod($tempFile, 0000);

        // Assert (before Act for exceptions)
        $this->expectException(ConfigException::class);
        $this->expectExceptionMessage("Failed to read local config file: {$tempFile}");

        // Act
        try {
            $this->processor->__invoke($context);
        } finally {
            // Clean up - restore permissions before deletion
            \chmod($tempFile, 0644);
            \unlink($tempFile);
        }
    }

    public function testInvokeSuccessfullyProcessesValidTomlFile(): void
    {
        // Arrange
        $tomlContent = 'binary_name = "custom-roadrunner"' . "\n" .
                      '[github.plugins.logger]' . "\n" .
                      'ref = "master"';
        $tempFile = $this->createTempFile($tomlContent);

        $this->veloxAction->configFile = $tempFile;
        $baseData = new TomlData(['existing' => 'base_data']);
        $context = new ConfigContext(
            $this->veloxAction,
            $this->buildDir,
            $baseData,
            ['original' => 'metadata'],
        );

        // Act
        $result = $this->processor->__invoke($context);

        // Assert
        self::assertNotSame($context, $result);

        $resultData = $result->tomlData->getData();
        self::assertSame('base_data', $resultData['existing']);
        self::assertSame('custom-roadrunner', $resultData['binary_name']);
        self::assertSame('master', $resultData['github']['plugins']['logger']['ref']);

        self::assertTrue($result->metadata['local_file_applied']);
        self::assertSame(\str_replace('\\', '/', $tempFile), $result->metadata['local_file_path']);
        self::assertSame('metadata', $result->metadata['original']);

        // Clean up
        \unlink($tempFile);
    }

    public function testInvokeMergesLocalDataWithExistingData(): void
    {
        // Arrange
        $tomlContent = '[roadrunner]' . "\n" .
                      'version = "2023.3.0"' . "\n" .
                      '[github.plugins.http]' . "\n" .
                      'ref = "v4.7.0"';
        $tempFile = $this->createTempFile($tomlContent);

        $this->veloxAction->configFile = $tempFile;
        $baseData = new TomlData([
            'roadrunner' => ['binary' => 'rr'],
            'github' => ['plugins' => ['logger' => ['ref' => 'v1.0.0']]],
        ]);
        $context = new ConfigContext(
            $this->veloxAction,
            $this->buildDir,
            $baseData,
            [],
        );

        // Act
        $result = $this->processor->__invoke($context);

        // Assert
        $resultData = $result->tomlData->getData();

        // Verify merge behavior
        self::assertSame('rr', $resultData['roadrunner']['binary']);
        self::assertSame('2023.3.0', $resultData['roadrunner']['version']);
        self::assertSame('v1.0.0', $resultData['github']['plugins']['logger']['ref']);
        self::assertSame('v4.7.0', $resultData['github']['plugins']['http']['ref']);

        // Clean up
        \unlink($tempFile);
    }

    #[DataProvider('provideValidTomlFiles')]
    public function testInvokeHandlesVariousTomlFormats(
        string $tomlContent,
        array $expectedKeys,
    ): void {
        // Arrange
        $tempFile = $this->createTempFile($tomlContent);
        $this->veloxAction->configFile = $tempFile;
        $context = new ConfigContext(
            $this->veloxAction,
            $this->buildDir,
            new TomlData(),
            [],
        );

        // Act
        $result = $this->processor->__invoke($context);

        // Assert
        $resultData = $result->tomlData->getData();

        if (\array_key_exists('_empty', $expectedKeys)) {
            // Special case for empty file test
            self::assertEmpty($resultData);
        } else {
            foreach ($expectedKeys as $key => $expectedValue) {
                self::assertArrayHasKey($key, $resultData);
                if ($expectedValue !== null) {
                    self::assertSame($expectedValue, $resultData[$key]);
                }
            }
        }

        // Clean up
        \unlink($tempFile);
    }

    public function testInvokePreservesContextImmutability(): void
    {
        // Arrange
        $tomlContent = 'new_key = "new_value"';
        $tempFile = $this->createTempFile($tomlContent);

        $this->veloxAction->configFile = $tempFile;
        $originalData = new TomlData(['original' => 'data']);
        $originalMetadata = ['original' => 'metadata'];
        $originalContext = new ConfigContext(
            $this->veloxAction,
            $this->buildDir,
            $originalData,
            $originalMetadata,
        );

        // Act
        $result = $this->processor->__invoke($originalContext);

        // Assert - Original context should remain unchanged
        self::assertSame(['original' => 'data'], $originalContext->tomlData->getData());
        self::assertSame(['original' => 'metadata'], $originalContext->metadata);
        self::assertSame($this->veloxAction, $originalContext->action);
        self::assertSame($this->buildDir, $originalContext->buildDir);

        // Result should have new data
        $resultData = $result->tomlData->getData();
        self::assertSame('data', $resultData['original']);
        self::assertSame('new_value', $resultData['new_key']);
        self::assertTrue($result->metadata['local_file_applied']);

        // Clean up
        \unlink($tempFile);
    }

    public function testInvokePreservesActionAndBuildDir(): void
    {
        // Arrange
        $tomlContent = 'test = "value"';
        $tempFile = $this->createTempFile($tomlContent);

        $this->veloxAction->configFile = $tempFile;
        $context = new ConfigContext(
            $this->veloxAction,
            $this->buildDir,
            new TomlData(),
            [],
        );

        // Act
        $result = $this->processor->__invoke($context);

        // Assert
        self::assertSame($this->veloxAction, $result->action);
        self::assertSame($this->buildDir, $result->buildDir);

        // Clean up
        \unlink($tempFile);
    }

    public function testInvokeAddsCorrectMetadata(): void
    {
        // Arrange
        $tomlContent = 'test_key = "test_value"';
        $tempFile = $this->createTempFile($tomlContent);

        $this->veloxAction->configFile = $tempFile;
        $originalMetadata = ['existing' => 'value', 'count' => 42];
        $context = new ConfigContext(
            $this->veloxAction,
            $this->buildDir,
            new TomlData(),
            $originalMetadata,
        );

        // Act
        $result = $this->processor->__invoke($context);

        // Assert
        self::assertTrue($result->metadata['local_file_applied']);
        self::assertSame(\str_replace('\\', '/', $tempFile), $result->metadata['local_file_path']);

        // Verify existing metadata is preserved
        self::assertSame('value', $result->metadata['existing']);
        self::assertSame(42, $result->metadata['count']);

        // Clean up
        \unlink($tempFile);
    }

    public function testInvokeWithRelativeConfigPath(): void
    {
        // Arrange
        $tomlContent = 'relative_test = "success"';
        $tempFile = $this->createTempFile($tomlContent);
        $relativePath = \basename($tempFile);

        // Change to temp directory to make relative path work
        $originalDir = \getcwd();
        \chdir(\dirname($tempFile));

        $this->veloxAction->configFile = $relativePath;
        $context = new ConfigContext(
            $this->veloxAction,
            $this->buildDir,
            new TomlData(),
            [],
        );

        // Act
        $result = $this->processor->__invoke($context);

        // Assert
        $resultData = $result->tomlData->getData();
        self::assertSame('success', $resultData['relative_test']);
        self::assertTrue($result->metadata['local_file_applied']);

        // Clean up
        \chdir($originalDir);
        \unlink($tempFile);
    }

    protected function setUp(): void
    {
        // Arrange (common setup)
        $this->processor = new LocalFileProcessor();
        $this->veloxAction = new VeloxAction();
        $this->buildDir = Path::create('/tmp/build');
    }

    private function createTempFile(string $content): string
    {
        $tempFile = \tempnam(\sys_get_temp_dir(), 'test_velox_');
        \file_put_contents($tempFile, $content);
        return $tempFile;
    }
}
