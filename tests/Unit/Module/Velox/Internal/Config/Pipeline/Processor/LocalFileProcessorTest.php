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

#[\Testo\Codecov\Covers(LocalFileProcessor::class)]
final class LocalFileProcessorTest
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

    #[\Testo\Test]
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
        \Testo\Assert::same($result, $originalContext);
    }

    #[\Testo\Test]
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
        \Testo\Expect::exception(ConfigException::class)->withMessage("Local config file not found: {$configPath}");

        // Act
        $this->processor->__invoke($context);
    }

    #[\Testo\Test]
    public function testInvokeThrowsExceptionWhenFileCannotBeRead(): void
    {
        // Skip this test on Windows as chmod doesn't work the same way
        if (PHP_OS_FAMILY === 'Windows') {
            throw new \Testo\Core\Exception\SkipTest('File permission tests are not reliable on Windows');
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
        \Testo\Expect::exception(ConfigException::class)->withMessage("Failed to read local config file: {$tempFile}");

        // Act
        try {
            $this->processor->__invoke($context);
        } finally {
            // Clean up - restore permissions before deletion
            \chmod($tempFile, 0644);
            \unlink($tempFile);
        }
    }

    #[\Testo\Test]
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
        \Testo\Assert::notSame($result, $context);

        $resultData = $result->tomlData->getData();
        \Testo\Assert::same($resultData['existing'], 'base_data');
        \Testo\Assert::same($resultData['binary_name'], 'custom-roadrunner');
        \Testo\Assert::same($resultData['github']['plugins']['logger']['ref'], 'master');

        \Testo\Assert::true($result->metadata['local_file_applied']);
        \Testo\Assert::same($result->metadata['local_file_path'], \str_replace('\\', '/', $tempFile));
        \Testo\Assert::same($result->metadata['original'], 'metadata');

        // Clean up
        \unlink($tempFile);
    }

    #[\Testo\Test]
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
        \Testo\Assert::same($resultData['roadrunner']['binary'], 'rr');
        \Testo\Assert::same($resultData['roadrunner']['version'], '2023.3.0');
        \Testo\Assert::same($resultData['github']['plugins']['logger']['ref'], 'v1.0.0');
        \Testo\Assert::same($resultData['github']['plugins']['http']['ref'], 'v4.7.0');

        // Clean up
        \unlink($tempFile);
    }

    #[\Testo\Data\DataProvider('provideValidTomlFiles')]
    #[\Testo\Test]
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
                    \Testo\Assert::same($resultData[$key], $expectedValue);
                }
            }
        }

        // Clean up
        \unlink($tempFile);
    }

    #[\Testo\Test]
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
        \Testo\Assert::same($originalContext->tomlData->getData(), ['original' => 'data']);
        \Testo\Assert::same($originalContext->metadata, ['original' => 'metadata']);
        \Testo\Assert::same($originalContext->action, $this->veloxAction);
        \Testo\Assert::same($originalContext->buildDir, $this->buildDir);

        // Result should have new data
        $resultData = $result->tomlData->getData();
        \Testo\Assert::same($resultData['original'], 'data');
        \Testo\Assert::same($resultData['new_key'], 'new_value');
        \Testo\Assert::true($result->metadata['local_file_applied']);

        // Clean up
        \unlink($tempFile);
    }

    #[\Testo\Test]
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
        \Testo\Assert::same($result->action, $this->veloxAction);
        \Testo\Assert::same($result->buildDir, $this->buildDir);

        // Clean up
        \unlink($tempFile);
    }

    #[\Testo\Test]
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
        \Testo\Assert::true($result->metadata['local_file_applied']);
        \Testo\Assert::same($result->metadata['local_file_path'], \str_replace('\\', '/', $tempFile));

        // Verify existing metadata is preserved
        \Testo\Assert::same($result->metadata['existing'], 'value');
        \Testo\Assert::same($result->metadata['count'], 42);

        // Clean up
        \unlink($tempFile);
    }

    #[\Testo\Test]
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
        \Testo\Assert::same($resultData['relative_test'], 'success');
        \Testo\Assert::true($result->metadata['local_file_applied']);

        // Clean up
        \chdir($originalDir);
        \unlink($tempFile);
    }

    #[\Testo\Lifecycle\BeforeTest]
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
