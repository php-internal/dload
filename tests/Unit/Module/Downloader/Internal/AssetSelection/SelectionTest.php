<?php

declare(strict_types=1);

namespace Internal\DLoad\Tests\Unit\Module\Downloader\Internal\AssetSelection;

use Internal\DLoad\Module\Downloader\Internal\AssetSelection\Candidate;
use Internal\DLoad\Module\Downloader\Internal\AssetSelection\Selection;
use Internal\DLoad\Module\Repository\AssetInterface;
use Internal\DLoad\Tests\Unit\Module\Downloader\Internal\AssetSelection\Stub\NamedAssets;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Test;

#[Covers(Selection::class)]
#[Covers(Candidate::class)]
final class SelectionTest
{
    #[Test]
    public function withoutRanksTheReleaseOrderIsKept(): void
    {
        Assert::same(self::names(self::selection('c', 'a', 'b')), ['c', 'a', 'b']);
    }

    #[Test]
    public function removedCandidatesAreGone(): void
    {
        $selection = self::selection('a', 'b', 'c')
            ->remove(static fn(Candidate $candidate): bool => $candidate->asset->getName() === 'b');

        Assert::same(self::names($selection), ['a', 'c']);
    }

    #[Test]
    public function anEarlierRankOutweighsALaterOne(): void
    {
        $selection = self::selection('a-slow', 'b-fast', 'c-fast')
            ->prefer(static fn(Candidate $candidate): bool => $candidate->asset->getName() !== 'b-fast')
            ->prefer(static fn(Candidate $candidate): bool => \str_ends_with($candidate->asset->getName(), 'fast'));

        Assert::same(self::names($selection), ['c-fast', 'a-slow', 'b-fast']);
    }

    #[Test]
    public function aLaterRankOnlyOrdersCandidatesEqualByTheEarlierOnes(): void
    {
        $selection = self::selection('aaa', 'b', 'cc')
            ->rank(static fn(Candidate $candidate): int => \strlen($candidate->asset->getName()));

        Assert::same(self::names($selection), ['b', 'cc', 'aaa']);
    }

    #[Test]
    public function equallyRankedCandidatesKeepTheReleaseOrder(): void
    {
        $selection = self::selection('b', 'a', 'c')->rank(static fn(): int => 1);

        Assert::same(self::names($selection), ['b', 'a', 'c']);
    }

    #[Test]
    public function aSelectionWithoutCandidatesIsEmpty(): void
    {
        $selection = self::selection('a')->remove(static fn(): bool => true);

        Assert::true($selection->isEmpty());
        Assert::same($selection->assets(), []);
    }

    /**
     * @param non-empty-string ...$names
     */
    private static function selection(string ...$names): Selection
    {
        return Selection::create(NamedAssets::create(...$names), '/.*/', null, strict: true);
    }

    /**
     * @return list<string>
     */
    private static function names(Selection $selection): array
    {
        return \array_map(static fn(AssetInterface $asset): string => $asset->getName(), $selection->assets());
    }
}
