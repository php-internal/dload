<?php

declare(strict_types=1);

namespace Internal\DLoad\Tests\Unit\Module\Velox\Internal\Config\Pipeline;

use Internal\DLoad\Module\Velox\Internal\Config\Pipeline\TomlData;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(TomlData::class)]
final class TomlDataTest extends TestCase
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

    public function testConstructorCreatesEmptyInstance(): void
    {
        // Act
        $tomlData = new TomlData();

        // Assert
        self::assertSame([], $tomlData->getData());
    }

    public function testConstructorCreatesInstanceWithData(): void
    {
        // Arrange
        $data = ['key' => 'value', 'section' => ['nested' => 'data']];

        // Act
        $tomlData = new TomlData($data);

        // Assert
        self::assertSame($data, $tomlData->getData());
    }

    public function testFromStringCreatesInstanceFromTomlString(): void
    {
        // Arrange
        $toml = "key = \"value\"\n\n[section]\nnested = \"data\"";
        $expectedData = [
            'key' => 'value',
            'section' => ['nested' => 'data'],
        ];

        // Act
        $tomlData = TomlData::fromString($toml);

        // Assert
        self::assertSame($expectedData, $tomlData->getData());
    }

    public function testMergeCreatesNewInstanceWithMergedData(): void
    {
        // Arrange
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

        // Act
        $merged = $tomlData1->merge($tomlData2);

        // Assert
        self::assertNotSame($tomlData1, $merged);
        self::assertNotSame($tomlData2, $merged);
        self::assertEquals($expectedMerged, $merged->getData());
    }

    public function testMergeOverwritesExistingKeys(): void
    {
        // Arrange
        $data1 = ['key' => 'original', 'section' => ['nested' => 'original']];
        $data2 = ['key' => 'updated', 'section' => ['nested' => 'updated']];
        $tomlData1 = new TomlData($data1);
        $tomlData2 = new TomlData($data2);
        $expectedMerged = [
            'key' => 'updated',
            'section' => ['nested' => 'updated'],
        ];

        // Act
        $merged = $tomlData1->merge($tomlData2);

        // Assert
        self::assertSame($expectedMerged, $merged->getData());
    }

    public function testMergeHandlesNestedArrays(): void
    {
        // Arrange
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

        // Act
        $merged = $tomlData1->merge($tomlData2);

        // Assert
        self::assertEquals($expectedMerged, $merged->getData());
    }

    #[DataProvider('provideSetPathData')]
    public function testSetCreatesNewInstanceWithUpdatedValue(string $path, mixed $value, array $expectedData): void
    {
        // Arrange
        $initialData = ['existing' => 'value'];
        $tomlData = new TomlData($initialData);

        // Act
        $updated = $tomlData->set($path, $value);

        // Assert
        self::assertNotSame($tomlData, $updated);
        self::assertSame($expectedData, $updated->getData());
        self::assertSame($initialData, $tomlData->getData()); // Original unchanged
    }

    public function testToTomlConvertsDataToTomlString(): void
    {
        // Arrange
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

        // Act
        $result = $tomlData->toToml();

        // Assert
        self::assertSame($expectedToml, $result);
    }

    public function testToTomlHandlesNestedSections(): void
    {
        // Arrange
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

        // Act
        $result = $tomlData->toToml();

        // Assert
        self::assertSame($expectedToml, $result);
    }

    public function testToTomlHandlesMixedSectionTypes(): void
    {
        // Arrange
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

        // Act
        $result = $tomlData->toToml();

        // Assert
        self::assertSame($expectedToml, $result);
    }

    public function testToTomlHandlesEmptyData(): void
    {
        // Arrange
        $tomlData = new TomlData();

        // Act
        $result = $tomlData->toToml();

        // Assert
        self::assertSame('', $result);
    }

    public function testRoundTripConversion(): void
    {
        // Arrange
        $originalToml = "key = \"value\"\n\n[section]\nnested = \"data\"";

        // Act
        $tomlData = TomlData::fromString($originalToml);
        $convertedToml = $tomlData->toToml();
        $roundTripData = TomlData::fromString($convertedToml);

        // Assert
        self::assertSame($tomlData->getData(), $roundTripData->getData());
    }

    #[DataProvider('provideQuotedValues')]
    public function testFromStringHandlesQuotedValues(string $toml, array $expectedData): void
    {
        // Act
        $tomlData = TomlData::fromString($toml);

        // Assert
        self::assertSame($expectedData, $tomlData->getData());
    }

    public function testImmutabilityOfOriginalData(): void
    {
        // Arrange
        $originalData = ['key' => 'value'];
        $tomlData = new TomlData($originalData);

        // Act
        $tomlData->set('newkey', 'newvalue');
        $tomlData->merge(new TomlData(['otherkey' => 'othervalue']));

        // Assert - original data and instance should be unchanged
        self::assertSame(['key' => 'value'], $tomlData->getData());
        self::assertSame(['key' => 'value'], $originalData);
    }

    public function testGetDataReturnsReadOnlyArray(): void
    {
        // Arrange
        $tomlData = new TomlData(['key' => 'value']);

        // Act
        $data = $tomlData->getData();

        // Assert
        self::assertSame(['key' => 'value'], $data);
    }

    public function testToTomlHandlesDeeplyNestedSections(): void
    {
        // Arrange
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

        // Act
        $result = $tomlData->toToml();

        // Assert
        self::assertSame($expectedToml, $result);
    }

    public function testToTomlHandlesInlineArrays(): void
    {
        // Arrange
        $data = [
            'features' => ['logging', 'caching', 'metrics'],
            'ports' => [8080, 9090, 3000],
        ];
        $tomlData = new TomlData($data);
        $expectedToml = <<<TOML
            features = ['logging', 'caching', 'metrics']
            ports = [8080, 9090, 3000]

            TOML;

        // Act
        $result = $tomlData->toToml();

        // Assert
        self::assertSame($expectedToml, $result);
    }

    public function testToTomlHandlesMixedTopLevelAndNestedStructures(): void
    {
        // Arrange
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

        // Act
        $result = $tomlData->toToml();

        // Assert
        self::assertSame($expectedToml, $result);
    }

    public function testFromStringAndToTomlRoundTripWithNestedArrays(): void
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

        // Act
        $tomlData = TomlData::fromString($originalToml);
        $convertedToml = $tomlData->toToml();
        $roundTripData = TomlData::fromString($convertedToml);

        // Assert
        self::assertSame($tomlData->getData(), $roundTripData->getData());
    }

    public function testParseTomlWithComplexNestedStructure(): void
    {
        // Arrange
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

        // Act
        $tomlData = TomlData::fromString($toml);

        // Assert
        self::assertSame($expectedData, $tomlData->getData());
    }
}
