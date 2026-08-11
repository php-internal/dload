<?php

declare(strict_types=1);

namespace Internal\DLoad\Tests\Unit\Module\Binary;

use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Data\DataProvider;
use Testo\Test;
use Internal\DLoad\Module\Binary\BinaryVersion;

#[Covers(BinaryVersion::class)]
final class BinaryVersionTest
{
    /**
     * Provides test cases for semantic version extraction.
     */
    public static function provideSemanticVersionOutputs(): \Generator
    {
        // Basic semantic versions
        yield 'simple semantic version' => [
            'App v1.2.3',
            '1.2.3',
            null,
        ];

        yield 'version with v prefix' => [
            'Version: v2.5.1',
            '2.5.1',
            null,
        ];

        yield 'version without v prefix' => [
            'Version: 3.7.12',
            '3.7.12',
            null,
        ];

        // Common version output formats
        yield 'CLI help with version' => [
            "MyApp CLI Tool\nVersion: 4.1.9\nUsage: myapp [options]",
            '4.1.9',
            null,
        ];

        yield 'verbose version output' => [
            "myapp version 2.0.10 (build 2023-04-15)\nCompiled with GCC 9.3.0",
            '2.0.10',
            null,
        ];

        // Version with pre-release or build metadata
        yield 'semver with pre-release' => [
            'Version 1.0.0-alpha.1',
            '1.0.0-alpha.1',
            '1.0.0',
        ];

        yield 'semver with build metadata' => [
            'App version 2.3.4+20230415',
            '2.3.4+20230415',
            '2.3.4+20230415',
        ];

        // Case insensitivity
        yield 'mixed case version string' => [
            'VERSION: 5.1.2',
            '5.1.2',
            null,
        ];

        // Spacing variations
        yield 'no space after version label' => [
            'version:1.0.5',
            '1.0.5',
            null,
        ];

        // RoadRunner
        yield 'roadrunner' => [
            'rr.exe version 2.12.3 (build time: 2023-02-16T13:08:35+0000, go1.20), OS: windows, arch: amd64',
            '2.12.3',
            null,
        ];

        // Protoc
        yield 'protoc' => [
            'libprotoc 30.2',
            '30.2',
            null,
        ];

        // Dolt
        yield 'dolt' => [
            'dolt version 1.51.1',
            '1.51.1',
            null,
        ];
    }

    /**
     * Provides test cases for fallback version extraction.
     */
    public static function provideFallbackVersionOutputs(): \Generator
    {
        // Single digit version
        yield 'single digit version' => [
            'Version: 7',
            '7',
        ];

        // Partial semantic version
        yield 'partial semver with two components' => [
            'Application version 2.0',
            '2.0',
        ];

        // Edge cases
        yield 'version with text suffix' => [
            'version: 5 beta',
            '5',
        ];

        // Null cases
        yield 'non-version digit' => [
            'There are 5 items available',
            null,
        ];
    }

    /**
     * Tests that the resolver correctly extracts semantic versions.
     */
    #[DataProvider('provideSemanticVersionOutputs')]
    #[Test]
    public function resolveVersionExtractsSemanticVersions(string $output, string $string, ?string $number): void
    {
        $result = BinaryVersion::fromBinaryOutput($output);

        Assert::same($result->string, $string);
        Assert::same($result->number, $number ?? $string);
    }

    /**
     * Tests that the resolver correctly extracts versions using fallback patterns.
     */
    #[DataProvider('provideFallbackVersionOutputs')]
    #[Test]
    public function resolveVersionExtractsVersionsWithFallbacks(string $output, ?string $number): void
    {
        $result = BinaryVersion::fromBinaryOutput($output);

        Assert::same($result->number, $number);
    }

    /**
     * Tests that the resolver returns null when no version can be extracted.
     */
    #[Test]
    public function resolveVersionReturnsNullWhenNoVersionFound(): void
    {
        $output = 'This output contains no version information.';

        $result = BinaryVersion::fromBinaryOutput($output);

        Assert::null($result->number);
    }
}
