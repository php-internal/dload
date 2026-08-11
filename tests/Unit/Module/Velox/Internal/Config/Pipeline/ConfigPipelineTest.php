<?php

declare(strict_types=1);

namespace Internal\DLoad\Tests\Unit\Module\Velox\Internal\Config\Pipeline;

use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Lifecycle\BeforeTest;
use Testo\Test;
use Internal\DLoad\Module\Config\Schema\Action\Velox as VeloxAction;
use Internal\DLoad\Module\Velox\Internal\Config\Pipeline\ConfigContext;
use Internal\DLoad\Module\Velox\Internal\Config\Pipeline\ConfigPipeline;
use Internal\DLoad\Module\Velox\Internal\Config\Pipeline\ConfigProcessor;
use Internal\DLoad\Module\Velox\Internal\Config\Pipeline\TomlData;
use Internal\Path;

#[Covers(ConfigPipeline::class)]
final class ConfigPipelineTest
{
    private VeloxAction $veloxAction;
    private Path $buildDir;

    #[Test]
    public function constructorCreatesInstanceWithProcessors(): void
    {
        $processor1 = $this->createMock(ConfigProcessor::class);
        $processor2 = $this->createMock(ConfigProcessor::class);
        $processors = [$processor1, $processor2];

        $pipeline = new ConfigPipeline($processors);

        Assert::instanceOf($pipeline, ConfigPipeline::class);
    }

    #[Test]
    public function constructorCreatesInstanceWithEmptyProcessors(): void
    {
        $processors = [];

        $pipeline = new ConfigPipeline($processors);

        Assert::instanceOf($pipeline, ConfigPipeline::class);
    }

    #[Test]
    public function processWithEmptyProcessorsReturnsOriginalContext(): void
    {
        $processors = [];
        $pipeline = new ConfigPipeline($processors);
        $originalContext = new ConfigContext(
            $this->veloxAction,
            $this->buildDir,
            new TomlData(['key' => 'value']),
            ['metadata' => 'test'],
        );

        $result = $pipeline->process($originalContext);

        Assert::same($result, $originalContext);
    }

    #[Test]
    public function processWithSingleProcessorCallsProcessor(): void
    {
        $processor = $this->createMock(ConfigProcessor::class);
        $originalContext = new ConfigContext(
            $this->veloxAction,
            $this->buildDir,
            new TomlData(['key' => 'value']),
            [],
        );
        $modifiedContext = new ConfigContext(
            $this->veloxAction,
            $this->buildDir,
            new TomlData(['key' => 'modified']),
            [],
        );

        $processor->expects(self::once())
            ->method('__invoke')
            ->with($originalContext)
            ->willReturn($modifiedContext);

        $pipeline = new ConfigPipeline([$processor]);

        $result = $pipeline->process($originalContext);

        Assert::same($result, $modifiedContext);
        Assert::notSame($result, $originalContext);
    }

    #[Test]
    public function processWithMultipleProcessorsCallsThemInSequence(): void
    {
        $processor1 = $this->createMock(ConfigProcessor::class);
        $processor2 = $this->createMock(ConfigProcessor::class);
        $processor3 = $this->createMock(ConfigProcessor::class);

        $originalContext = new ConfigContext(
            $this->veloxAction,
            $this->buildDir,
            new TomlData(['step' => '0']),
            [],
        );
        $context1 = new ConfigContext(
            $this->veloxAction,
            $this->buildDir,
            new TomlData(['step' => '1']),
            [],
        );
        $context2 = new ConfigContext(
            $this->veloxAction,
            $this->buildDir,
            new TomlData(['step' => '2']),
            [],
        );
        $finalContext = new ConfigContext(
            $this->veloxAction,
            $this->buildDir,
            new TomlData(['step' => '3']),
            [],
        );

        // Set up expectations for sequential processing
        $processor1->expects(self::once())
            ->method('__invoke')
            ->with($originalContext)
            ->willReturn($context1);

        $processor2->expects(self::once())
            ->method('__invoke')
            ->with($context1)
            ->willReturn($context2);

        $processor3->expects(self::once())
            ->method('__invoke')
            ->with($context2)
            ->willReturn($finalContext);

        $pipeline = new ConfigPipeline([$processor1, $processor2, $processor3]);

        $result = $pipeline->process($originalContext);

        Assert::same($result, $finalContext);
        Assert::same($result->tomlData->getData(), ['step' => '3']);
    }

    #[Test]
    public function processPassesThroughComplexContextChanges(): void
    {
        $processor1 = $this->createMock(ConfigProcessor::class);
        $processor2 = $this->createMock(ConfigProcessor::class);

        $originalTomlData = new TomlData(['initial' => 'data']);
        $originalMetadata = ['version' => '1.0'];
        $originalContext = new ConfigContext(
            $this->veloxAction,
            $this->buildDir,
            $originalTomlData,
            $originalMetadata,
        );

        // First processor modifies TOML data
        $intermediateTomlData = new TomlData(['initial' => 'data', 'added_by_p1' => 'value1']);
        $intermediateContext = new ConfigContext(
            $this->veloxAction,
            $this->buildDir,
            $intermediateTomlData,
            $originalMetadata,
        );

        // Second processor modifies metadata
        $finalMetadata = ['version' => '1.0', 'processed_by' => 'p2'];
        $finalContext = new ConfigContext(
            $this->veloxAction,
            $this->buildDir,
            $intermediateTomlData,
            $finalMetadata,
        );

        $processor1->expects(self::once())
            ->method('__invoke')
            ->with($originalContext)
            ->willReturn($intermediateContext);

        $processor2->expects(self::once())
            ->method('__invoke')
            ->with($intermediateContext)
            ->willReturn($finalContext);

        $pipeline = new ConfigPipeline([$processor1, $processor2]);

        $result = $pipeline->process($originalContext);

        Assert::same($result, $finalContext);
        Assert::same($result->tomlData->getData(), ['initial' => 'data', 'added_by_p1' => 'value1']);
        Assert::same($result->metadata, ['version' => '1.0', 'processed_by' => 'p2']);
    }

    #[Test]
    public function processPreservesContextImmutability(): void
    {
        $processor = $this->createMock(ConfigProcessor::class);
        $originalTomlData = new TomlData(['original' => 'data']);
        $originalMetadata = ['original' => 'metadata'];
        $originalContext = new ConfigContext(
            $this->veloxAction,
            $this->buildDir,
            $originalTomlData,
            $originalMetadata,
        );

        $modifiedContext = new ConfigContext(
            $this->veloxAction,
            $this->buildDir,
            new TomlData(['modified' => 'data']),
            ['modified' => 'metadata'],
        );

        $processor->expects(self::once())
            ->method('__invoke')
            ->with($originalContext)
            ->willReturn($modifiedContext);

        $pipeline = new ConfigPipeline([$processor]);

        $result = $pipeline->process($originalContext);

        // Assert - Original context should remain unchanged
        Assert::same($originalContext->tomlData->getData(), ['original' => 'data']);
        Assert::same($originalContext->metadata, ['original' => 'metadata']);

        // Result should have modified data
        Assert::same($result->tomlData->getData(), ['modified' => 'data']);
        Assert::same($result->metadata, ['modified' => 'metadata']);
    }

    #[Test]
    public function processWithProcessorThatReturnsUnchangedContext(): void
    {
        $processor1 = $this->createMock(ConfigProcessor::class);
        $processor2 = $this->createMock(ConfigProcessor::class);
        $processor3 = $this->createMock(ConfigProcessor::class);

        $originalContext = new ConfigContext(
            $this->veloxAction,
            $this->buildDir,
            new TomlData(['data' => 'original']),
            [],
        );

        $modifiedContext = new ConfigContext(
            $this->veloxAction,
            $this->buildDir,
            new TomlData(['data' => 'modified_by_p1']),
            [],
        );

        $finalContext = new ConfigContext(
            $this->veloxAction,
            $this->buildDir,
            new TomlData(['data' => 'modified_by_p3']),
            [],
        );

        // First processor modifies context
        $processor1->expects(self::once())
            ->method('__invoke')
            ->with($originalContext)
            ->willReturn($modifiedContext);

        // Second processor returns context unchanged (simulating conditional processing)
        $processor2->expects(self::once())
            ->method('__invoke')
            ->with($modifiedContext)
            ->willReturn($modifiedContext);

        // Third processor modifies context again
        $processor3->expects(self::once())
            ->method('__invoke')
            ->with($modifiedContext)
            ->willReturn($finalContext);

        $pipeline = new ConfigPipeline([$processor1, $processor2, $processor3]);

        $result = $pipeline->process($originalContext);

        Assert::same($result, $finalContext);
        Assert::same($result->tomlData->getData(), ['data' => 'modified_by_p3']);
    }

    #[Test]
    public function processMaintainsActionAndBuildDirThroughPipeline(): void
    {
        $processor = $this->createMock(ConfigProcessor::class);
        $originalContext = new ConfigContext(
            $this->veloxAction,
            $this->buildDir,
            new TomlData(['test' => 'data']),
            ['test' => 'metadata'],
        );

        // Processor only modifies TOML data and metadata, not action or buildDir
        $modifiedContext = new ConfigContext(
            $this->veloxAction,
            $this->buildDir,
            new TomlData(['modified' => 'data']),
            ['modified' => 'metadata'],
        );

        $processor->expects(self::once())
            ->method('__invoke')
            ->with($originalContext)
            ->willReturn($modifiedContext);

        $pipeline = new ConfigPipeline([$processor]);

        $result = $pipeline->process($originalContext);

        Assert::same($result->action, $this->veloxAction);
        Assert::same($result->buildDir, $this->buildDir);
        Assert::same($result->tomlData->getData(), ['modified' => 'data']);
        Assert::same($result->metadata, ['modified' => 'metadata']);
    }

    #[Test]
    public function processHandlesLargeNumberOfProcessors(): void
    {
        $processors = [];
        $expectedValue = 0;

        // Create 10 processors that each increment a counter in the TOML data
        for ($i = 0; $i < 10; $i++) {
            $processor = $this->createMock(ConfigProcessor::class);
            $expectedValue = $i + 1;

            $processor->expects(self::once())
                ->method('__invoke')
                ->willReturnCallback(static function (ConfigContext $context) use ($expectedValue): ConfigContext {
                    return $context->withTomlData(new TomlData(['counter' => $expectedValue]));
                });

            $processors[] = $processor;
        }

        $originalContext = new ConfigContext(
            $this->veloxAction,
            $this->buildDir,
            new TomlData(['counter' => 0]),
            [],
        );

        $pipeline = new ConfigPipeline($processors);

        $result = $pipeline->process($originalContext);

        Assert::same($result->tomlData->getData(), ['counter' => 10]);
    }

    #[BeforeTest]
    protected function prepare(): void
    {
        $this->veloxAction = new VeloxAction();
        $this->buildDir = Path::create('/tmp/build');
    }
}
