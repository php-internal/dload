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
    public function candidatesRemovedWithAReasonAreKeptUnderIt(): void
    {
        $selection = self::selection('a', 'b', 'c', 'd')
            ->remove(static fn(Candidate $candidate): bool => $candidate->asset->getName() === 'a')
            ->remove(static fn(Candidate $candidate): bool => $candidate->asset->getName() === 'b', 'package')
            ->rank('length', static fn(): int => 0)
            ->remove(static fn(Candidate $candidate): bool => $candidate->asset->getName() === 'c', 'package')
            ->remove(static fn(): bool => false, 'nothing');

        Assert::same(self::names($selection), ['d']);
        Assert::same(\array_keys($selection->removed), ['package']);
        Assert::same(
            \array_map(static fn(Candidate $candidate): string => $candidate->asset->getName(), $selection->removed['package']),
            ['b', 'c'],
        );
    }

    #[Test]
    public function anEarlierRankOutweighsALaterOne(): void
    {
        $selection = self::selection('a-slow', 'b-fast', 'c-fast')
            ->prefer('first', static fn(Candidate $candidate): bool => $candidate->asset->getName() !== 'b-fast')
            ->prefer('second', static fn(Candidate $candidate): bool => \str_ends_with($candidate->asset->getName(), 'fast'));

        Assert::same(self::names($selection), ['c-fast', 'a-slow', 'b-fast']);
    }

    #[Test]
    public function aLaterRankOnlyOrdersCandidatesEqualByTheEarlierOnes(): void
    {
        $selection = self::selection('aaa', 'b', 'cc')
            ->rank('length', static fn(Candidate $candidate): int => \strlen($candidate->asset->getName()));

        Assert::same(self::names($selection), ['b', 'cc', 'aaa']);
    }

    #[Test]
    public function equallyRankedCandidatesKeepTheReleaseOrder(): void
    {
        $selection = self::selection('b', 'a', 'c')->rank('same', static fn(): int => 1);

        Assert::same(self::names($selection), ['b', 'a', 'c']);
    }

    #[Test]
    public function ranksAreKeptUnderTheirNames(): void
    {
        $selection = self::selection('a')
            ->rank('os', static fn(): int => 2)
            ->prefer('libc', static fn(): bool => true);

        Assert::same($selection->sorted()[0]->ranks, ['os' => 2, 'libc' => 0]);
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
