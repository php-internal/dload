<?php

declare(strict_types=1);

namespace Internal\DLoad\Tests\Unit\Module\Binary;

use Internal\DLoad\Module\Binary\BinaryVersion;
use Internal\DLoad\Module\Common\Stability;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Data\DataProvider;
use Testo\Data\DataSet;
use Testo\Test;

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

        yield 'pre-release without a separator' => [
            'tool 2.0.0rc1 (linux)',
            '2.0.0rc1',
            '2.0.0',
        ];

        yield 'word glued to the number' => [
            'tool 1.2.3_amd64',
            '1.2.3',
            null,
        ];

        yield 'word starting with a stability letter glued to the number' => [
            'tool 1.2.3arm64',
            '1.2.3',
            null,
        ];

        yield 'platform after a pre-release glued to the number' => [
            'tool 2.0.0rc1_linux',
            '2.0.0rc1_linux',
            '2.0.0',
        ];

        yield 'letter release glued to the number' => [
            'OpenSSL 1.1.1b  26 Feb 2019',
            '1.1.1',
            null,
        ];

        yield 'beta abbreviation glued to the number' => [
            'tool 1.0.0b2',
            '1.0.0b2',
            '1.0.0',
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

        // More than three parts
        yield 'four-part version' => [
            'tool version 1.2.3.4 (build 2025-01-01)',
            '1.2.3.4',
            null,
        ];

        yield 'five-part version with pre-release' => [
            'Version: v1.2.3.4.5-beta.1',
            '1.2.3.4.5-beta.1',
            '1.2.3.4.5',
        ];

        yield 'four-part version after a name' => [
            'libtool 30.2.1.4',
            '30.2.1.4',
            null,
        ];

        // Other numbers around the version
        yield 'build number after the version' => [
            'tool 1.2.3 build 4567',
            '1.2.3',
            null,
        ];

        yield 'date after the version' => [
            'tool 1.2.3 20250101',
            '1.2.3',
            null,
        ];

        yield 'date joined to the version' => [
            'tool 1.2.3-20250101',
            '1.2.3-20250101',
            '1.2.3',
        ];

        yield 'build metadata after a pre-release' => [
            'v1.2.3-rc.1+build.5',
            '1.2.3-rc.1',
            '1.2.3',
        ];

        yield 'number before the version' => [
            'protocol 2 tool 1.2.3',
            '1.2.3',
            null,
        ];

        yield 'Go toolchain before the version' => [
            'built with go1.21 tool 1.2.3',
            '1.2.3',
            null,
        ];

        yield 'Go toolchain after the version' => [
            'tool 1.2.3 (go1.22.1)',
            '1.2.3',
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

    #[DataSet(['OpenSSL 1.1.1a  20 Nov 2018'], 'patch letter a')]
    #[DataSet(['OpenSSL 1.1.1b  26 Feb 2019'], 'patch letter b')]
    #[DataSet(['OpenSSL 1.1.1c  28 May 2019'], 'patch letter c')]
    #[Test]
    public function patchLetterIsAStableRelease(string $output): void
    {
        $result = BinaryVersion::fromBinaryOutput($output);

        Assert::same($result->string, '1.1.1');
        Assert::same($result->stability, Stability::Stable);
    }

    #[DataSet(['tool 1.0.0b2', Stability::Beta], 'beta abbreviation')]
    #[DataSet(['tool 1.2.3a1', Stability::Alpha], 'alpha abbreviation')]
    #[DataSet(['tool 2.0.0rc1', Stability::RC], 'release candidate')]
    #[Test]
    public function preReleaseGluedToTheNumberKeepsItsStability(string $output, Stability $stability): void
    {
        Assert::same(BinaryVersion::fromBinaryOutput($output)->stability, $stability);
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
