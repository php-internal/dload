<?php

declare(strict_types=1);

namespace Internal\DLoad\Tests\Unit\Module\Version;

use Internal\DLoad\Module\Common\Stability;
use Internal\DLoad\Module\Version\PreRelease;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Data\DataProvider;
use Testo\Test;

#[Covers(PreRelease::class)]
final class PreReleaseTest
{
    public static function provideValidPreReleases(): \Generator
    {
        yield 'dotted beta' => ['beta.1', Stability::Beta, 1];
        yield 'upper case release candidate' => ['RC1', Stability::RC, 1];
        yield 'dashed release candidate' => ['rc-3', Stability::RC, 3];
        yield 'underscored alpha' => ['alpha_2', Stability::Alpha, 2];
        yield 'beta abbreviation' => ['b4', Stability::Beta, 4];
        yield 'alpha abbreviation' => ['A5', Stability::Alpha, 5];
        yield 'pre' => ['pre.3', Stability::Pre, 3];
        yield 'preview' => ['preview5', Stability::Preview, 5];
        yield 'unstable' => ['unstable7', Stability::Unstable, 7];
        yield 'dev' => ['dev.2', Stability::Dev, 2];
        yield 'snapshot' => ['snapshot1', Stability::Snapshot, 1];
        yield 'nightly date' => ['nightly20250503', Stability::Nightly, 20250503];
        yield 'no number' => ['beta', Stability::Beta, 0];
    }

    public static function provideInvalidPreReleases(): \Generator
    {
        yield 'empty' => [''];
        yield 'feature' => ['feature'];
        yield 'number first' => ['1beta'];
        yield 'two numbers' => ['beta.1.2'];
        yield 'letters after the number' => ['beta1x'];
        yield 'two separators' => ['beta..1'];
    }

    public static function provideSpellings(): \Generator
    {
        yield 'dot and none' => ['rc.1', 'RC1'];
        yield 'dash and dot' => ['beta-2', 'beta.2'];
        yield 'abbreviation and keyword' => ['b3', 'beta.3'];
        yield 'leading zero' => ['alpha.01', 'alpha1'];
        yield 'no number and zero' => ['beta', 'beta.0'];
    }

    #[DataProvider('provideValidPreReleases')]
    #[Test]
    public function fromStringReadsTheStabilityAndTheNumber(string $value, Stability $stability, int $number): void
    {
        $preRelease = PreRelease::fromString($value);

        Assert::same($preRelease?->stability, $stability);
        Assert::same($preRelease?->number, $number);
    }

    #[DataProvider('provideInvalidPreReleases')]
    #[Test]
    public function fromStringRejectsAnythingButAStabilityWithANumber(string $value): void
    {
        Assert::null(PreRelease::fromString($value));
    }

    #[DataProvider('provideSpellings')]
    #[Test]
    public function spellingDoesNotMatter(string $a, string $b): void
    {
        Assert::true(PreRelease::fromString($a)?->equals(PreRelease::fromString($b)) ?? false);
    }

    #[Test]
    public function ordersByStabilityWeightThenByNumber(): void
    {
        $ordered = [
            'nightly20250503', 'snapshot1', 'dev.1', 'unstable1', 'alpha.1', 'alpha.10',
            'preview1', 'beta', 'beta.1', 'beta.2', 'beta.10', 'pre.1', 'RC1', 'rc.2', 'stable',
        ];
        $shuffled = [
            'beta.10', 'stable', 'alpha.1', 'nightly20250503', 'RC1', 'beta', 'dev.1', 'pre.1',
            'beta.2', 'snapshot1', 'rc.2', 'preview1', 'alpha.10', 'unstable1', 'beta.1',
        ];

        $preReleases = \array_map(static fn(string $value): PreRelease => PreRelease::fromString($value), $shuffled);
        \usort($preReleases, static fn(PreRelease $a, PreRelease $b): int => $a->compare($b));

        $expected = \array_map(static fn(string $value): string => (string) PreRelease::fromString($value), $ordered);
        Assert::same(\array_map(\strval(...), $preReleases), $expected);
    }

    #[Test]
    public function stringsToTheCanonicalSpelling(): void
    {
        Assert::same((string) PreRelease::fromString('RC-1'), 'RC.1');
        Assert::same((string) PreRelease::fromString('b2'), 'beta.2');
        Assert::same((string) PreRelease::fromString('beta'), 'beta');
    }
}
