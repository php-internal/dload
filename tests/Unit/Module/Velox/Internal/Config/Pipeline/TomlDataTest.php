<?php

declare(strict_types=1);

namespace Internal\DLoad\Tests\Unit\Module\Velox\Internal\Config\Pipeline;

use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Data\DataProvider;
use Testo\Test;
use Internal\DLoad\Module\Velox\Internal\Config\Pipeline\TomlData;

#[Covers(TomlData::class)]
final class TomlDataTest
{
    public static function provideSetPathData(): \Generator
    {
        yield 'simple key' => [
            'newkey',
            'newvalue',
            ['existing' => 'value', 'newkey' => 'newvalue'],
        ];

        yield 'nested path' => [
            'section.nested',
            'data',
            ['existing' => 'value', 'section' => ['nested' => 'data']],
        ];

        yield 'deeply nested path' => [
            'a.b.c.d',
            'deep',
            ['existing' => 'value', 'a' => ['b' => ['c' => ['d' => 'deep']]]],
        ];

        yield 'overwrite existing top-level key' => [
            'existing',
            'updated',
            ['existing' => 'updated'],
        ];
    }

    public static function provideQuotedValues(): \Generator
    {
        yield 'double quotes' => [
            'key = "value with spaces"',
            ['key' => 'value with spaces'],
        ];

        yield 'single quotes' => [
            "key = 'value with spaces'",
            ['key' => 'value with spaces'],
        ];

        yield 'mixed quotes in section' => [
            <<<TOML
                [section]
                double = "quoted"
                single = 'quoted'
                TOML,
            [
                'section' => [
                    'double' => 'quoted',
                    'single' => 'quoted',
                ],
            ],
        ];
    }

    #[Test]
    public function constructorCreatesEmptyInstance(): void
    {
        $tomlData = new TomlData();

        Assert::same($tomlData->getData(), []);
    }

    #[Test]
    public function constructorCreatesInstanceWithData(): void
    {
        $data = ['key' => 'value', 'section' => ['nested' => 'data']];

        $tomlData = new TomlData($data);

        Assert::same($tomlData->getData(), $data);
    }

    #[Test]
    public function fromStringCreatesInstanceFromTomlString(): void
    {
        $toml = "key = \"value\"\n\n[section]\nnested = \"data\"";
        $expectedData = [
            'key' => 'value',
            'section' => ['nested' => 'data'],
        ];

        $tomlData = TomlData::fromString($toml);

        Assert::same($tomlData->getData(), $expectedData);
    }

    #[Test]
    public function mergeCreatesNewInstanceWithMergedData(): void
    {
        $data1 = ['key1' => 'value1', 'section' => ['nested1' => 'data1']];
        $data2 = ['key2' => 'value2', 'section' => ['nested2' => 'data2']];
        $tomlData1 = new TomlData($data1);
        $tomlData2 = new TomlData($data2);
        $expectedMerged = [
            'key1' => 'value1',
            'key2' => 'value2',
            'section' => [
                'nested1' => 'data1',
                'nested2' => 'data2',
            ],
        ];

        $merged = $tomlData1->merge($tomlData2);

        Assert::notSame($merged, $tomlData1);
        Assert::notSame($merged, $tomlData2);
        Assert::equals($merged->getData(), $expectedMerged);
    }

    #[Test]
    public function mergeOverwritesExistingKeys(): void
    {
        $data1 = ['key' => 'original', 'section' => ['nested' => 'original']];
        $data2 = ['key' => 'updated', 'section' => ['nested' => 'updated']];
        $tomlData1 = new TomlData($data1);
        $tomlData2 = new TomlData($data2);
        $expectedMerged = [
            'key' => 'updated',
            'section' => ['nested' => 'updated'],
        ];

        $merged = $tomlData1->merge($tomlData2);

        Assert::same($merged->getData(), $expectedMerged);
    }

    #[Test]
    public function mergeHandlesNestedArrays(): void
    {
        $data1 = ['section' => ['key1' => 'value1', 'nested' => ['deep1' => 'data1']]];
        $data2 = ['section' => ['key2' => 'value2', 'nested' => ['deep2' => 'data2']]];
        $tomlData1 = new TomlData($data1);
        $tomlData2 = new TomlData($data2);
        $expectedMerged = [
            'section' => [
                'key1' => 'value1',
                'key2' => 'value2',
                'nested' => [
                    'deep1' => 'data1',
                    'deep2' => 'data2',
                ],
            ],
        ];

        $merged = $tomlData1->merge($tomlData2);

        Assert::equals($merged->getData(), $expectedMerged);
    }

    #[DataProvider('provideSetPathData')]
    #[Test]
    public function setCreatesNewInstanceWithUpdatedValue(string $path, mixed $value, array $expectedData): void
    {
        $initialData = ['existing' => 'value'];
        $tomlData = new TomlData($initialData);

        $updated = $tomlData->set($path, $value);

        Assert::notSame($updated, $tomlData);
        Assert::same($updated->getData(), $expectedData);
        Assert::same($tomlData->getData(), $initialData); // Original unchanged
    }

    #[Test]
    public function toTomlConvertsDataToTomlString(): void
    {
        $data = [
            'key1' => 'value1',
            'key2' => 'value2',
            'section' => [
                'nested1' => 'data1',
                'nested2' => 'data2',
            ],
        ];
        $tomlData = new TomlData($data);
        $expectedToml = <<<TOML
            key1 = 'value1'
            key2 = 'value2'

            [section]
            nested1 = 'data1'
            nested2 = 'data2'

            TOML;

        $result = $tomlData->toToml();

        Assert::same($result, $expectedToml);
    }

    #[Test]
    public function toTomlHandlesNestedSections(): void
    {
        $data = [
            'github' => [
                'plugins' => [
                    'logger' => 'enabled',
                    'cache' => 'disabled',
                ],
            ],
        ];
        $tomlData = new TomlData($data);
        $expectedToml = "[github.plugins]\nlogger = 'enabled'\ncache = 'disabled'\n";

        $result = $tomlData->toToml();

        Assert::same($result, $expectedToml);
    }

    #[Test]
    public function toTomlHandlesMixedSectionTypes(): void
    {
        $data = [
            'roadrunner' => [
                'simple' => 'value',
                'plugins' => ['logger' => 'enabled'],
            ],
        ];
        $tomlData = new TomlData($data);
        $expectedToml = <<<TOML
            roadrunner.simple = 'value'

            [roadrunner.plugins]
            logger = 'enabled'

            TOML;

        $result = $tomlData->toToml();

        Assert::same($result, $expectedToml);
    }

    #[Test]
    public function toTomlHandlesEmptyData(): void
    {
        $tomlData = new TomlData();

        $result = $tomlData->toToml();

        Assert::same($result, '');
    }

    #[Test]
    public function roundTripConversion(): void
    {
        $originalToml = "key = \"value\"\n\n[section]\nnested = \"data\"";

        $tomlData = TomlData::fromString($originalToml);
        $convertedToml = $tomlData->toToml();
        $roundTripData = TomlData::fromString($convertedToml);

        Assert::same($roundTripData->getData(), $tomlData->getData());
    }

    #[DataProvider('provideQuotedValues')]
    #[Test]
    public function fromStringHandlesQuotedValues(string $toml, array $expectedData): void
    {
        $tomlData = TomlData::fromString($toml);

        Assert::same($tomlData->getData(), $expectedData);
    }

    #[Test]
    public function immutabilityOfOriginalData(): void
    {
        $originalData = ['key' => 'value'];
        $tomlData = new TomlData($originalData);

        $tomlData->set('newkey', 'newvalue');
        $tomlData->merge(new TomlData(['otherkey' => 'othervalue']));

        // Assert - original data and instance should be unchanged
        Assert::same($tomlData->getData(), ['key' => 'value']);
        Assert::same($originalData, ['key' => 'value']);
    }

    #[Test]
    public function getDataReturnsReadOnlyArray(): void
    {
        $tomlData = new TomlData(['key' => 'value']);

        $data = $tomlData->getData();

        Assert::same($data, ['key' => 'value']);
    }

    #[Test]
    public function toTomlHandlesDeeplyNestedSections(): void
    {
        $data = [
            'github' => [
                'plugins' => [
                    'logger' => [
                        'ref' => 'v5.1.8',
                        'owner' => 'roadrunner-server',
                        'repository' => 'logger',
                    ],
                    'server' => [
                        'ref' => 'v5.2.9',
                        'owner' => 'roadrunner-server',
                        'repository' => 'server',
                    ],
                ],
            ],
        ];
        $tomlData = new TomlData($data);
        $expectedToml = <<<TOML
            [github.plugins.logger]
            ref = 'v5.1.8'
            owner = 'roadrunner-server'
            repository = 'logger'

            [github.plugins.server]
            ref = 'v5.2.9'
            owner = 'roadrunner-server'
            repository = 'server'

            TOML;

        $result = $tomlData->toToml();

        Assert::same($result, $expectedToml);
    }

    #[Test]
    public function toTomlHandlesInlineArrays(): void
    {
        $data = [
            'features' => ['logging', 'caching', 'metrics'],
            'ports' => [8080, 9090, 3000],
        ];
        $tomlData = new TomlData($data);
        $expectedToml = <<<TOML
            features = ['logging', 'caching', 'metrics']
            ports = [8080, 9090, 3000]

            TOML;

        $result = $tomlData->toToml();

        Assert::same($result, $expectedToml);
    }

    #[Test]
    public function toTomlHandlesMixedTopLevelAndNestedStructures(): void
    {
        $data = [
            'roadrunner' => [
                'ref' => 'v2025.1.1',
            ],
            'log' => [
                'level' => 'debug',
                'mode' => 'dev',
            ],
            'github' => [
                'token' => [
                    'token' => '${GITHUB_TOKEN}',
                ],
                'plugins' => [
                    'logger' => [
                        'ref' => 'v5.1.8',
                        'owner' => 'roadrunner-server',
                        'repository' => 'logger',
                    ],
                ],
            ],
        ];
        $tomlData = new TomlData($data);
        $expectedToml = <<<TOML
            [roadrunner]
            ref = 'v2025.1.1'

            [log]
            level = 'debug'
            mode = 'dev'

            [github.token]
            token = '\${GITHUB_TOKEN}'

            [github.plugins.logger]
            ref = 'v5.1.8'
            owner = 'roadrunner-server'
            repository = 'logger'

            TOML;

        $result = $tomlData->toToml();

        Assert::same($result, $expectedToml);
    }

    #[Test]
    public function fromStringAndToTomlRoundTripWithNestedArrays(): void
    {
        // Arrange - This mimics the structure from velox.toml
        $originalToml = <<<TOML
            [roadrunner]
            ref = "v2025.1.1"

            [log]
            level = "debug"
            mode = "dev"

            [github.token]
            token = "\${GITHUB_TOKEN}"

            [github.plugins.logger]
            ref = "v5.1.8"
            owner = "roadrunner-server"
            repository = "logger"

            [github.plugins.server]
            ref = "v5.2.9"
            owner = "roadrunner-server"
            repository = "server"
            TOML;

        $tomlData = TomlData::fromString($originalToml);
        $convertedToml = $tomlData->toToml();
        $roundTripData = TomlData::fromString($convertedToml);

        Assert::same($roundTripData->getData(), $tomlData->getData());
    }

    #[Test]
    public function parseTomlWithComplexNestedStructure(): void
    {
        $toml = <<<TOML
            [roadrunner]
            ref = "v2025.1.1"

            [github.plugins.logger]
            ref = "v5.1.8"
            owner = "roadrunner-server"
            repository = "logger"

            [github.plugins.temporal]
            ref = "v5.7.0"
            owner = "temporalio"
            repository = "roadrunner-temporal"
            TOML;

        $expectedData = [
            'roadrunner' => ['ref' => 'v2025.1.1'],
            'github' => [
                'plugins' => [
                    'logger' => [
                        'ref' => 'v5.1.8',
                        'owner' => 'roadrunner-server',
                        'repository' => 'logger',
                    ],
                    'temporal' => [
                        'ref' => 'v5.7.0',
                        'owner' => 'temporalio',
                        'repository' => 'roadrunner-temporal',
                    ],
                ],
            ],
        ];

        $tomlData = TomlData::fromString($toml);

        Assert::same($tomlData->getData(), $expectedData);
    }
}
