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
    public function processReturnsOriginalContextWhenConfigFileIsNull(): void
    {
        $this->veloxAction->configFile = null;
        $originalContext = new ConfigContext(
            $this->veloxAction,
            $this->buildDir,
            new TomlData(['base' => 'data']),
        );

        $result = $this->process($originalContext);

        Assert::same($result, $originalContext);
    }

    #[Test]
    public function processThrowsExceptionWhenConfigFileDoesNotExist(): void
    {
        $configPath = '/non/existent/path.toml';
        $this->veloxAction->configFile = $configPath;
        $context = new ConfigContext(
            $this->veloxAction,
            $this->buildDir,
            new TomlData(),
        );

        Expect::exception(ConfigException::class)->withMessage("Local config file not found: {$configPath}");

        $this->process($context);
    }

    #[Test]
    public function processThrowsExceptionWhenFileCannotBeRead(): void
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
        );

        // Make file unreadable by changing permissions
        \chmod($tempFile, 0000);

        Expect::exception(ConfigException::class)->withMessageContaining("Failed to read local config file: {$tempFile}");

        try {
            $this->process($context);
        } finally {
            // Clean up - restore permissions before deletion
            \chmod($tempFile, 0644);
            \unlink($tempFile);
        }
    }

    #[Test]
    public function processSuccessfullyProcessesValidTomlFile(): void
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
        );

        $result = $this->process($context);

        Assert::notSame($result, $context);

        $resultData = $result->tomlData->getData();
        Assert::same($resultData['existing'], 'base_data');
        Assert::same($resultData['binary_name'], 'custom-roadrunner');
        Assert::same($resultData['github']['plugins']['logger']['ref'], 'master');

        // Clean up
        \unlink($tempFile);
    }

    #[Test]
    public function processMergesLocalDataWithExistingData(): void
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
        );

        $result = $this->process($context);

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
    public function processHandlesVariousTomlFormats(
        string $tomlContent,
        array $expectedKeys,
    ): void {
        $tempFile = $this->createTempFile($tomlContent);
        $this->veloxAction->configFile = $tempFile;
        $context = new ConfigContext(
            $this->veloxAction,
            $this->buildDir,
            new TomlData(),
        );

        $result = $this->process($context);

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
    public function processPreservesContextImmutability(): void
    {
        $tomlContent = 'new_key = "new_value"';
        $tempFile = $this->createTempFile($tomlContent);

        $this->veloxAction->configFile = $tempFile;
        $originalData = new TomlData(['original' => 'data']);
        $originalContext = new ConfigContext(
            $this->veloxAction,
            $this->buildDir,
            $originalData,
        );

        $result = $this->process($originalContext);

        // Assert - Original context should remain unchanged
        Assert::same($originalContext->tomlData->getData(), ['original' => 'data']);
        Assert::same($originalContext->action, $this->veloxAction);
        Assert::same($originalContext->buildDir, $this->buildDir);

        // Result should have new data
        $resultData = $result->tomlData->getData();
        Assert::same($resultData['original'], 'data');
        Assert::same($resultData['new_key'], 'new_value');

        // Clean up
        \unlink($tempFile);
    }

    #[Test]
    public function processPreservesActionAndBuildDir(): void
    {
        $tomlContent = 'test = "value"';
        $tempFile = $this->createTempFile($tomlContent);

        $this->veloxAction->configFile = $tempFile;
        $context = new ConfigContext(
            $this->veloxAction,
            $this->buildDir,
            new TomlData(),
        );

        $result = $this->process($context);

        Assert::same($result->action, $this->veloxAction);
        Assert::same($result->buildDir, $this->buildDir);

        // Clean up
        \unlink($tempFile);
    }

    #[Test]
    public function processWithRelativeConfigPath(): void
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
        );

        $result = $this->process($context);

        $resultData = $result->tomlData->getData();
        Assert::same($resultData['relative_test'], 'success');

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

    /**
     * Fails unless the processor passes the context on exactly once.
     */
    private function process(ConfigContext $context): ConfigContext
    {
        $calls = 0;
        $result = $this->processor->process(
            $context,
            static function (ConfigContext $context) use (&$calls): ConfigContext {
                ++$calls;
                return $context;
            },
        );

        Assert::same($calls, 1);

        return $result;
    }

    private function createTempFile(string $content): string
    {
        $tempFile = \tempnam(\sys_get_temp_dir(), 'test_velox_');
        \file_put_contents($tempFile, $content);
        return $tempFile;
    }
}
