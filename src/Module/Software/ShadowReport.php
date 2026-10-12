<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\Software;

use Internal\DLoad\Module\Config\Schema\Embed\Repository;
use Internal\DLoad\Service\Logger;

/**
 * Tells which Composer package entries lost to the entry that is about to be downloaded.
 *
 * Reported only for software that is actually downloaded, so a clash on software nobody requests
 * stays silent:
 *
 * - an official entry shadowing a package entry: the download source differs from what the package
 *   declares;
 * - several packages declaring the id: a list of every `<package>#<id>` to pick one explicitly.
 *
 * Both are warnings, and only for entries with other repositories: the same repositories are no clash.
 *
 * A `dload.xml` entry shadows silently: that is the project's own choice. So does an explicit
 * `<package>#<id>`.
 *
 * ```php
 * $report->report($collection, 'gh');
 * ```
 *
 * @internal
 */
final class ShadowReport
{
    public function __construct(
        private readonly Logger $logger,
    ) {}

    /**
     * @param non-empty-string $id The id the download was requested with.
     */
    public function report(SoftwareCollection $collection, string $id): void
    {
        $winner = \str_contains($id, '#') ? null : $collection->find($id);
        if ($winner === null || $winner->origin->kind === OriginKind::Config) {
            return;
        }

        $packages = \array_values(\array_filter(
            $collection->candidates($id),
            static fn(Entry $entry): bool => $entry->origin->kind === OriginKind::Package,
        ));

        $clash = \array_values(\array_filter(
            $packages,
            static fn(Entry $entry): bool => self::sourceKey($entry) !== self::sourceKey($winner),
        ));
        if ($clash === []) {
            return;
        }

        if ($winner->origin->kind === OriginKind::Official) {
            $this->logger->warning(
                "The official registry shadows `%s` declared by %s.\nTo download the package's one, use %s.",
                $id,
                \implode(', ', \array_map(static fn(Entry $entry): string => (string) $entry->origin, $clash)),
                \implode(' or ', \array_map(static fn(Entry $entry): string => self::address($entry), $clash)),
            );
            return;
        }

        $width = \max(0, ...\array_map(static fn(Entry $entry): int => \strlen(self::address($entry)), $packages));
        $lines = \array_map(
            static fn(Entry $entry): string => \sprintf(
                '  %s    %s',
                \str_pad(self::address($entry), $width),
                \implode(', ', \array_map(
                    static fn(Repository $repository): string => $repository->uri,
                    $entry->software->repositories,
                )),
            ),
            $packages,
        );

        $this->logger->warning(
            "Several packages declare `%s`, using %s.\nTo pick one explicitly, use one of:\n%s",
            $id,
            self::address($winner),
            \implode("\n", $lines),
        );
    }

    /**
     * @return non-empty-string
     */
    private static function address(Entry $entry): string
    {
        return (string) $entry->origin . '#' . $entry->getId();
    }

    /**
     * Identifies where an entry downloads from: its set of repositories, order ignored.
     *
     * Other differences, such as the binary or asset patterns, do not change the source.
     */
    private static function sourceKey(Entry $entry): string
    {
        $keys = \array_map(
            static fn(Repository $repository): string => \strtolower($repository->type) . ' ' . self::normalizeUri($repository),
            $entry->software->repositories,
        );
        $keys = \array_unique($keys);
        \sort($keys);

        return \implode("\n", $keys);
    }

    /**
     * Brings the spellings of one repository to one form: `owner/repo`, `github.com/owner/repo`
     * and `https://github.com/owner/repo.git` are the same repository.
     */
    private static function normalizeUri(Repository $repository): string
    {
        $uri = \strtolower(\trim($repository->uri));
        $uri = (string) \preg_replace('~^[a-z][a-z0-9+.-]*://~', '', $uri);
        $uri = \rtrim($uri, '/');
        \str_ends_with($uri, '.git') and $uri = \substr($uri, 0, -4);
        $uri = (string) \preg_replace('~^www\.~', '', $uri);

        $publicHost = match (\strtolower($repository->type)) {
            'github' => 'github.com/',
            'gitlab' => 'gitlab.com/',
            default => null,
        };

        return $publicHost !== null && \str_starts_with($uri, $publicHost)
            ? \substr($uri, \strlen($publicHost))
            : $uri;
    }
}
