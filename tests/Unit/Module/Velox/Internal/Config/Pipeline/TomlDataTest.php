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
    public static function provideNestedSectionData(): \Generator
    {
        yield 'simple nested section' => [
            "[github.plugins.logger]\ntype = \"logger\"",
            ['github' => ['plugins' => ['logger' => ['type' => 'logger']]]],
        ];

        yield 'multiple nested sections' => [
            "[github.plugins.logger]\ntype = \"logger\"\n\n[github.plugins.cache]\ntype = \"cache\"",
            [
                'github' => [
                    'plugins' => [
                        'logger' => ['type' => 'logger'],
                        'cache' => ['type' => 'cache'],
                    ],
                ],
            ],
        ];

        yield 'deeply nested section' => [
            "[a.b.c.d]\nvalue = \"deep\"",
            ['a' => ['b' => ['c' => ['d' => ['value' => 'deep']]]]],
        ];
    }

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

        yield 'no quotes' => [
            'key = simple_value',
            ['key' => 'simple_value'],
        ];

        yield 'mixed quotes in section' => [
            "[section]\ndouble = \"quoted\"\nsingle = 'quoted'\nbare = unquoted",
            [
                'section' => [
                    'double' => 'quoted',
                    'single' => 'quoted',
                    'bare' => 'unquoted',
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

    public function testFromStringHandlesEmptyString(): void
    {
        // Act
        $tomlData = TomlData::fromString('');

        // Assert
        self::assertSame([], $tomlData->getData());
    }

    public function testFromStringHandlesCommentsAndEmptyLines(): void
    {
        // Arrange
        $toml = "# This is a comment\n\nkey = \"value\"\n# Another comment\n\n[section]\n# Comment in section\nnested = \"data\"";
        $expectedData = [
            'key' => 'value',
            'section' => ['nested' => 'data'],
        ];

        // Act
        $tomlData = TomlData::fromString($toml);

        // Assert
        self::assertSame($expectedData, $tomlData->getData());
    }

    #[DataProvider('provideNestedSectionData')]
    public function testFromStringHandlesNestedSections(string $toml, array $expectedData): void
    {
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
            key1 = "value1"
            key2 = "value2"

            [section]
            nested1 = "data1"
            nested2 = "data2"
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
        $expectedToml = "[github.plugins]\nlogger = \"enabled\"\ncache = \"disabled\"";

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
            [roadrunner]
            simple = "value"

            [roadrunner.plugins]
            logger = "enabled"
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

    public function testMergeTomlStringsReturnsMergedTomlString(): void
    {
        // Arrange
        $localToml = "local_key = \"local_value\"\n\n[roadrunner]\nversion = \"1.0\"";
        $remoteToml = "remote_key = \"remote_value\"\n\n[github.plugins]\nlogger = \"enabled\"";
        $expectedMerged = "local_key = \"local_value\"\nremote_key = \"remote_value\"\n\n[roadrunner]\nversion = \"1.0\"\n\n[github.plugins]\nlogger = \"enabled\"";

        // Act
        $result = TomlData::mergeTomlStrings($localToml, $remoteToml);

        // Assert
        self::assertSame($expectedMerged, $result);
    }

    public function testMergeTomlStringsHandlesEmptyStrings(): void
    {
        // Arrange
        $localToml = "key = \"value\"";
        $emptyToml = "";

        // Act
        $result1 = TomlData::mergeTomlStrings($localToml, $emptyToml);
        $result2 = TomlData::mergeTomlStrings($emptyToml, $localToml);

        // Assert
        self::assertSame("key = \"value\"", $result1);
        self::assertSame("key = \"value\"", $result2);
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
            ref = "v5.1.8"
            owner = "roadrunner-server"
            repository = "logger"

            [github.plugins.server]
            ref = "v5.2.9"
            owner = "roadrunner-server"
            repository = "server"
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
            features = ["logging", "caching", "metrics"]
            ports = ["8080", "9090", "3000"]
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

    public function testToTomlHandlesEmptyNestedSections(): void
    {
        // Arrange
        $data = [
            'section' => [
                'empty_subsection' => [],
                'populated_subsection' => ['key' => 'value'],
            ],
        ];
        $tomlData = new TomlData($data);
        $expectedToml = <<<TOML
            [section.empty_subsection]

            [section.populated_subsection]
            key = "value"
            TOML;

        // Act
        $result = $tomlData->toToml();

        // Assert
        self::assertSame($expectedToml, $result);
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

    public function testToTomlOrdersSectionsWithRoadrunnerFirst(): void
    {
        // Arrange - sections in different order
        $data = [
            'github' => ['plugins' => ['logger' => 'enabled']],
            'log' => ['level' => 'debug'],
            'roadrunner' => ['ref' => 'v2025.1.1'],
            'debug' => ['enabled' => 'true'],
            'other' => ['key' => 'value'],
        ];
        $tomlData = new TomlData($data);

        // Act
        $result = $tomlData->toToml();

        // Assert - roadrunner should be first, then debug, log, github, then others
        $expectedToml = <<<TOML
            [roadrunner]
            ref = "v2025.1.1"

            [debug]
            enabled = "true"

            [log]
            level = "debug"

            [github.plugins]
            logger = "enabled"

            [other]
            key = "value"
            TOML;

        self::assertSame($expectedToml, $result);
    }

    public function testToTomlNormalizesRoadrunnerRefWithVPrefix(): void
    {
        // Arrange - ref without "v" prefix
        $data = [
            'roadrunner' => ['ref' => '2025.1.1'],
        ];
        $tomlData = new TomlData($data);

        // Act
        $result = $tomlData->toToml();

        // Assert - "v" prefix should be added
        $expectedToml = <<<TOML
            [roadrunner]
            ref = "v2025.1.1"
            TOML;

        self::assertSame($expectedToml, $result);
    }

    public function testToTomlPreservesExistingVPrefixInRoadrunnerRef(): void
    {
        // Arrange - ref already has "v" prefix
        $data = [
            'roadrunner' => ['ref' => 'v2025.1.1'],
        ];
        $tomlData = new TomlData($data);

        // Act
        $result = $tomlData->toToml();

        // Assert - "v" prefix should be preserved
        $expectedToml = <<<TOML
            [roadrunner]
            ref = "v2025.1.1"
            TOML;

        self::assertSame($expectedToml, $result);
    }

    public function testToTomlHandlesEmptyRoadrunnerRef(): void
    {
        // Arrange - empty ref
        $data = [
            'roadrunner' => ['ref' => ''],
        ];
        $tomlData = new TomlData($data);

        // Act
        $result = $tomlData->toToml();

        // Assert - empty ref should remain empty
        $expectedToml = <<<TOML
            [roadrunner]
            ref = ""
            TOML;

        self::assertSame($expectedToml, $result);
    }

    public function testToTomlHandlesNonStringRoadrunnerRef(): void
    {
        // Arrange - non-string ref
        $data = [
            'roadrunner' => ['ref' => 123],
        ];
        $tomlData = new TomlData($data);

        // Act
        $result = $tomlData->toToml();

        // Assert - non-string ref should remain unchanged
        $expectedToml = <<<TOML
            [roadrunner]
            ref = "123"
            TOML;

        self::assertSame($expectedToml, $result);
    }

    public function testToTomlWithCompleteConfigurationOrdering(): void
    {
        // Arrange - complex configuration with multiple sections
        $data = [
            'other' => ['key' => 'value'],
            'gitlab' => ['url' => 'gitlab.com'],
            'github' => ['token' => 'secret'],
            'log' => ['level' => 'info'],
            'debug' => ['enabled' => 'false'],
            'roadrunner' => ['ref' => '2025.1.1'],
        ];
        $tomlData = new TomlData($data);

        // Act
        $result = $tomlData->toToml();

        // Assert - proper ordering and normalization
        $expectedToml = <<<TOML
            [roadrunner]
            ref = "v2025.1.1"

            [debug]
            enabled = "false"

            [log]
            level = "info"

            [github]
            token = "secret"

            [gitlab]
            url = "gitlab.com"

            [other]
            key = "value"
            TOML;

        self::assertSame($expectedToml, $result);
    }
}
