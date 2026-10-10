<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\Downloader\Internal\AssetSelection;

use Internal\DLoad\Module\Config\Schema\Action\Type;
use Internal\DLoad\Module\Repository\AssetInterface;

/**
 * Assets of one release passed through the selection rules.
 *
 * Rules either remove a candidate or give every candidate a rank. Ranks are compared in the
 * order the rules added them, so an earlier rule outweighs any later one, and a rank only orders
 * candidates the earlier ranks left equal.
 *
 * @internal
 * @psalm-internal Internal\DLoad\Module\Downloader
 */
final class Selection
{
    /**
     * @param list<Candidate> $candidates
     * @param non-empty-string $assetPattern Pattern the asset names must match.
     * @param Type|null $type Download action type restricting the asset format.
     * @param bool $strict Whether assets for another OS or architecture are removed rather than ranked lower.
     * @param array<non-empty-string, list<Candidate>> $removed Candidates removed with a reason, by the reason,
     *        for a failure report to name them.
     */
    private function __construct(
        public readonly array $candidates,
        public readonly string $assetPattern,
        public readonly ?Type $type,
        public readonly bool $strict,
        public readonly array $removed = [],
    ) {}

    /**
     * @param iterable<AssetInterface> $assets
     * @param non-empty-string $assetPattern
     */
    public static function create(iterable $assets, string $assetPattern, ?Type $type, bool $strict): self
    {
        $candidates = [];
        foreach ($assets as $asset) {
            $candidates[] = new Candidate($asset, AssetName::fromString($asset->getName()), \count($candidates));
        }

        return new self($candidates, $assetPattern, $type, $strict);
    }

    /**
     * Removes the candidates matching the predicate.
     *
     * The predicate looks at one candidate only: a decision that depends on the other candidates
     * is a rank, see {@see self::rank()}.
     *
     * @param \Closure(Candidate): bool $predicate
     * @param non-empty-string|null $reason Key to keep the removed candidates under, see {@see self::$removed}.
     */
    public function remove(\Closure $predicate, ?string $reason = null): self
    {
        $kept = $removed = [];
        foreach ($this->candidates as $candidate) {
            if ($predicate($candidate)) {
                $removed[] = $candidate;
            } else {
                $kept[] = $candidate;
            }
        }

        return $this->withCandidates(
            $kept,
            $reason === null || $removed === []
                ? $this->removed
                : [...$this->removed, $reason => [...$this->removed[$reason] ?? [], ...$removed]],
        );
    }

    /**
     * Gives every candidate a rank; lower is better.
     *
     * @param non-empty-string $key Name of the rank, unique within the selection.
     * @param \Closure(Candidate): int $rank
     */
    public function rank(string $key, \Closure $rank): self
    {
        return $this->withCandidates(\array_map(
            static fn(Candidate $candidate): Candidate => $candidate->withRank($key, $rank($candidate)),
            $this->candidates,
        ));
    }

    /**
     * Moves the candidates matching the predicate ahead of the others.
     *
     * @param non-empty-string $key Name of the rank, unique within the selection.
     * @param \Closure(Candidate): bool $predicate
     */
    public function prefer(string $key, \Closure $predicate): self
    {
        return $this->rank($key, static fn(Candidate $candidate): int => $predicate($candidate) ? 0 : 1);
    }

    public function isEmpty(): bool
    {
        return $this->candidates === [];
    }

    /**
     * Candidates from the best to the worst.
     *
     * @return list<Candidate>
     */
    public function sorted(): array
    {
        $candidates = $this->candidates;
        \usort(
            $candidates,
            static fn(Candidate $a, Candidate $b): int => [...\array_values($a->ranks), $a->position]
                <=> [...\array_values($b->ranks), $b->position],
        );

        return $candidates;
    }

    /**
     * Assets from the best to the worst.
     *
     * @return list<AssetInterface>
     */
    public function assets(): array
    {
        return \array_map(static fn(Candidate $candidate): AssetInterface => $candidate->asset, $this->sorted());
    }

    /**
     * @param list<Candidate> $candidates
     * @param array<non-empty-string, list<Candidate>>|null $removed
     */
    private function withCandidates(array $candidates, ?array $removed = null): self
    {
        return new self($candidates, $this->assetPattern, $this->type, $this->strict, $removed ?? $this->removed);
    }
}
