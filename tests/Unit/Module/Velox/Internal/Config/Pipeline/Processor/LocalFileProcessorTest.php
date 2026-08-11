<?php

declare(strict_types=1);

namespace Internal\DLoad\Tests\Unit\Module\Velox\Internal\Config\Pipeline\Processor;

use Internal\DLoad\Module\Config\Schema\Action\Velox as VeloxAction;
use Internal\DLoad\Module\Velox\Exception\Config as ConfigException;
use Internal\DLoad\Module\Velox\Internal\Config\Pipeline\ConfigContext;
use Internal\DLoad\Module\Velox\Internal\Config\Pipeline\Processor\LocalFileProcessor;
use Internal\DLoad\Module\Velox\Internal\Config\Pipeline\TomlData;
use Internal\Path;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Core\Exception\SkipTest;
use Testo\Data\DataProvider;
use Testo\Expect;
use Testo\Lifecycle\BeforeTest;
use Testo\Test;

#[Covers(LocalFileProcessor::class)]
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

    #[Test]
    public function invokeReturnsOriginalContextWhenConfigFileIsNull(): void
    {
        $this->veloxAction->configFile = null;
        $originalContext = new ConfigContext(
            $this->veloxAction,
            $this->buildDir,
            new TomlData(['base' => 'data']),
            ['initial' => 'metadata'],
        );

        $result = $this->processor->__invoke($originalContext);

        Assert::same($result, $originalContext);
    }

    #[Test]
    public function invokeThrowsExceptionWhenConfigFileDoesNotExist(): void
    {
        $configPath = '/non/existent/path.toml';
        $this->veloxAction->configFile = $configPath;
        $context = new ConfigContext(
            $this->veloxAction,
            $this->buildDir,
            new TomlData(),
            [],
        );

        Expect::exception(ConfigException::class)->withMessage("Local config file not found: {$configPath}");

        $this->processor->__invoke($context);
    }

    #[Test]
    public function invokeThrowsExceptionWhenFileCannotBeRead(): void
    {
        // Skip this test on Windows as chmod doesn't work the same way
        if (PHP_OS_FAMILY === 'Windows') {
            throw new SkipTest('File permission tests are not reliable on Windows');
        }

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

        Expect::exception(ConfigException::class)->withMessage("Failed to read local config file: {$tempFile}");

        try {
            $this->processor->__invoke($context);
        } finally {
            // Clean up - restore permissions before deletion
            \chmod($tempFile, 0644);
            \unlink($tempFile);
        }
    }

    #[Test]
    public function invokeSuccessfullyProcessesValidTomlFile(): void
    {
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

        $result = $this->processor->__invoke($context);

        Assert::notSame($result, $context);

        $resultData = $result->tomlData->getData();
        Assert::same($resultData['existing'], 'base_data');
        Assert::same($resultData['binary_name'], 'custom-roadrunner');
        Assert::same($resultData['github']['plugins']['logger']['ref'], 'master');

        Assert::true($result->metadata['local_file_applied']);
        Assert::same($result->metadata['local_file_path'], \str_replace('\\', '/', $tempFile));
        Assert::same($result->metadata['original'], 'metadata');

        // Clean up
        \unlink($tempFile);
    }

    #[Test]
    public function invokeMergesLocalDataWithExistingData(): void
    {
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

        $result = $this->processor->__invoke($context);

        $resultData = $result->tomlData->getData();

        // Verify merge behavior
        Assert::same($resultData['roadrunner']['binary'], 'rr');
        Assert::same($resultData['roadrunner']['version'], '2023.3.0');
        Assert::same($resultData['github']['plugins']['logger']['ref'], 'v1.0.0');
        Assert::same($resultData['github']['plugins']['http']['ref'], 'v4.7.0');

        // Clean up
        \unlink($tempFile);
    }

    #[DataProvider('provideValidTomlFiles')]
    #[Test]
    public function invokeHandlesVariousTomlFormats(
        string $tomlContent,
        array $expectedKeys,
    ): void {
        $tempFile = $this->createTempFile($tomlContent);
        $this->veloxAction->configFile = $tempFile;
        $context = new ConfigContext(
            $this->veloxAction,
            $this->buildDir,
            new TomlData(),
            [],
        );

        $result = $this->processor->__invoke($context);

        $resultData = $result->tomlData->getData();

        if (\array_key_exists('_empty', $expectedKeys)) {
            // Special case for empty file test
            Assert::blank($resultData);
        } else {
            foreach ($expectedKeys as $key => $expectedValue) {
                Assert::array($resultData)->hasKeys($key);
                if ($expectedValue !== null) {
                    Assert::same($resultData[$key], $expectedValue);
                }
            }
        }

        // Clean up
        \unlink($tempFile);
    }

    #[Test]
    public function invokePreservesContextImmutability(): void
    {
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

        $result = $this->processor->__invoke($originalContext);

        // Assert - Original context should remain unchanged
        Assert::same($originalContext->tomlData->getData(), ['original' => 'data']);
        Assert::same($originalContext->metadata, ['original' => 'metadata']);
        Assert::same($originalContext->action, $this->veloxAction);
        Assert::same($originalContext->buildDir, $this->buildDir);

        // Result should have new data
        $resultData = $result->tomlData->getData();
        Assert::same($resultData['original'], 'data');
        Assert::same($resultData['new_key'], 'new_value');
        Assert::true($result->metadata['local_file_applied']);

        // Clean up
        \unlink($tempFile);
    }

    #[Test]
    public function invokePreservesActionAndBuildDir(): void
    {
        $tomlContent = 'test = "value"';
        $tempFile = $this->createTempFile($tomlContent);

        $this->veloxAction->configFile = $tempFile;
        $context = new ConfigContext(
            $this->veloxAction,
            $this->buildDir,
            new TomlData(),
            [],
        );

        $result = $this->processor->__invoke($context);

        Assert::same($result->action, $this->veloxAction);
        Assert::same($result->buildDir, $this->buildDir);

        // Clean up
        \unlink($tempFile);
    }

    #[Test]
    public function invokeAddsCorrectMetadata(): void
    {
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

        $result = $this->processor->__invoke($context);

        Assert::true($result->metadata['local_file_applied']);
        Assert::same($result->metadata['local_file_path'], \str_replace('\\', '/', $tempFile));

        // Verify existing metadata is preserved
        Assert::same($result->metadata['existing'], 'value');
        Assert::same($result->metadata['count'], 42);

        // Clean up
        \unlink($tempFile);
    }

    #[Test]
    public function invokeWithRelativeConfigPath(): void
    {
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

        $result = $this->processor->__invoke($context);

        $resultData = $result->tomlData->getData();
        Assert::same($resultData['relative_test'], 'success');
        Assert::true($result->metadata['local_file_applied']);

        // Clean up
        \chdir($originalDir);
        \unlink($tempFile);
    }

    #[BeforeTest]
    protected function prepare(): void
    {
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
