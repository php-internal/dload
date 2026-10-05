<?php

declare(strict_types=1);

namespace Internal\DLoad\Tests\Unit\Module\Downloader\Internal\AssetSelection\Stub;

use Internal\DLoad\Module\Config\Schema\Action\Type;
use Internal\DLoad\Module\Downloader\Internal\AssetSelection\AssetRule;
use Internal\DLoad\Module\Downloader\Internal\AssetSelection\Candidate;
use Internal\DLoad\Module\Downloader\Internal\AssetSelection\Selection;

/**
 * Runs one asset rule on its own, with `$next` returning the selection it receives.
 */
final class RuleRunner
{
    /**
     * @param list<non-empty-string> $assets Asset names in release order.
     * @param non-empty-string $pattern
     */
    public static function run(
        AssetRule $rule,
        array $assets,
        bool $strict = true,
        ?Type $type = null,
        string $pattern = '/.*/',
    ): Selection {
        $calls = 0;
        $result = $rule->select(
            Selection::create(NamedAssets::create(...$assets), $pattern, $type, $strict),
            static function (Selection $selection) use (&$calls): Selection {
                ++$calls;
                return $selection;
            },
        );

        $calls === 1 or throw new \LogicException(\sprintf('The rule called `$next` %d times.', $calls));
        return $result;
    }

    /**
     * Names of the candidates left, in release order.
     *
     * @return list<string>
     */
    public static function names(Selection $selection): array
    {
        return \array_map(static fn(Candidate $candidate): string => $candidate->asset->getName(), $selection->candidates);
    }

    /**
     * The rank the rule gave each candidate under the key, by asset name.
     *
     * @param non-empty-string $key
     * @return array<string, int|null>
     */
    public static function ranks(Selection $selection, string $key): array
    {
        $ranks = [];
        foreach ($selection->candidates as $candidate) {
            $ranks[$candidate->asset->getName()] = $candidate->ranks[$key] ?? null;
        }

        return $ranks;
    }
}
