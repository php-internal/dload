<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\Downloader\Internal\Diagnostics;

/**
 * Collected information about an attempt to download software from a single repository.
 *
 * @internal
 */
final class RepositoryAttempt
{
    /** Maximum number of releases described in the report. */
    private const RELEASES_LIMIT = 3;

    /** Maximum number of release names listed as available. */
    private const FETCHED_RELEASES_LIMIT = 10;

    /** @var \Throwable|null Repository level failure, e.g. an API error */
    public ?\Throwable $error = null;

    /** @var int<0, max>|null Number of releases that match the version constraint and stability */
    public ?int $matchedReleases = null;

    /** @var list<string> Release names fetched from the repository */
    private array $fetchedReleases = [];

    /** @var list<ReleaseAttempt> */
    private array $releases = [];

    /** @var int<0, max> Number of releases processed but not included into the report */
    private int $hiddenReleases = 0;

    /**
     * @param non-empty-string $type Repository type, e.g. `github`
     * @param non-empty-string $name Repository name, e.g. `owner/repo`
     * @param non-empty-string $assetPattern Asset name pattern from the configuration
     */
    public function __construct(
        public readonly string $type,
        public readonly string $name,
        public readonly string $assetPattern,
    ) {}

    /**
     * @param list<string> $names Release names available in the repository
     */
    public function registerFetchedReleases(array $names): void
    {
        $this->fetchedReleases = $names;
    }

    /**
     * @param non-empty-string $name Release name or tag
     */
    public function addRelease(string $name): ReleaseAttempt
    {
        $attempt = new ReleaseAttempt($name);

        if (\count($this->releases) < self::RELEASES_LIMIT) {
            $this->releases[] = $attempt;
        } else {
            ++$this->hiddenReleases;
        }

        return $attempt;
    }

    /**
     * Renders repository details as report lines.
     *
     * @return list<string>
     */
    public function describe(): array
    {
        $lines = [\sprintf('%s `%s`', $this->type, $this->name)];

        if ($this->error !== null) {
            foreach (\explode("\n", $this->error->getMessage()) as $line) {
                $lines[] = '  ' . $line;
            }
        }

        $this->matchedReleases === null or $lines[] = \sprintf(
            '  %d release(s) match the requested version and stability.',
            $this->matchedReleases,
        );

        if ($this->matchedReleases === 0 && $this->fetchedReleases !== []) {
            $listed = \array_slice($this->fetchedReleases, 0, self::FETCHED_RELEASES_LIMIT);
            $lines[] = \sprintf('  Releases available in the repository: %s', \implode(', ', $listed));
        }

        if ($this->releases !== []) {
            $lines[] = '  Checked releases:';
            foreach ($this->releases as $release) {
                foreach ($release->describe() as $index => $line) {
                    $lines[] = $index === 0 ? '    - ' . $line : '    ' . $line;
                }
            }

            $this->hiddenReleases === 0 or $lines[] = \sprintf('    and %d more release(s)', $this->hiddenReleases);
        }

        return $lines;
    }
}
