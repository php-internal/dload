<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\Archive;

use Closure as ArchiveMatcher;
use Internal\DLoad\Module\Archive\Internal\PharArchive;
use Internal\DLoad\Module\Archive\Internal\TarPharArchive;
use Internal\DLoad\Module\Archive\Internal\ZipPharArchive;

/**
 * Factory for creating archive handlers
 *
 * Creates appropriate archive handlers based on file extension or custom matchers.
 *
 * ```php
 * $factory = new ArchiveFactory();
 * $archive = $factory->create(new \SplFileInfo('archive.zip'));
 * foreach ($archive->extract() as $path => $fileInfo) {
 *     // Process extracted files
 * }
 * ```
 *
 * @psalm-type ArchiveMatcher = \Closure(\SplFileInfo): ?Archive
 */
final class ArchiveFactory
{
    /** @var list<non-empty-string> List of supported file extensions */
    private array $extensions = [];

    /** @var array<ArchiveMatcher> List of archive type matchers */
    private array $matchers = [];

    /**
     * Creates factory with default archive type handlers
     */
    public function __construct()
    {
        $this->bootDefaultMatchers();
    }

    /**
     * Extends factory with custom archive matcher
     *
     * Adds a custom matcher to the beginning of the matchers list.
     *
     * ```php
     * $factory->extend(
     *     fn(\SplFileInfo $file) => str_ends_with($file->getFilename(), '.rar')
     *         ? new RarArchive($file)
     *         : null,
     *     ['rar']
     * );
     * ```
     *
     * @param \Closure $matcher Function that creates archive handler or returns null
     * @param list<non-empty-string> $extensions List of supported extensions
     */
    public function extend(\Closure $matcher, array $extensions = []): void
    {
        \array_unshift($this->matchers, $matcher);
        $this->extensions = \array_unique(\array_merge($this->extensions, $extensions));
    }

    /**
     * Creates archive handler for the given file
     *
     * @param \SplFileInfo $file Archive file
     * @return Archive Archive handler
     * @throws \InvalidArgumentException When no suitable archive handler found
     */
    public function create(\SplFileInfo $file): Archive
    {
        $errors = [];

        foreach ($this->matchers as $matcher) {
            try {
                if ($archive = $matcher($file)) {
                    return $archive;
                }
            } catch (\Throwable $e) {
                $errors[] = '  - ' . $e->getMessage();
                continue;
            }
        }

        $error = \sprintf("Can not open the archive \"%s\":\n%s", $file->getFilename(), \implode(\PHP_EOL, $errors));

        throw new \InvalidArgumentException($error);
    }

    /**
     * Returns list of supported archive extensions
     *
     * @return list<non-empty-string>
     */
    public function getSupportedExtensions(): array
    {
        return $this->extensions;
    }

    /**
     * Registers default archive type handlers
     */
    private function bootDefaultMatchers(): void
    {
        $this->extend($this->matcher(
            'zip',
            static fn(\SplFileInfo $info): Archive => new ZipPharArchive($info),
        ), ['zip']);

        $this->extend($this->matcher(
            'tar.gz',
            static fn(\SplFileInfo $info): Archive => new TarPharArchive($info),
        ), ['tar.gz']);

        $this->extend($this->matcher(
            'phar',
            static fn(\SplFileInfo $info): Archive => new PharArchive($info),
        ), ['phar']);
    }

    /**
     * Creates a matcher function for files with specific extension
     *
     * @param string $extension File extension to match
     * @param ArchiveMatcher $then Function to create archive handler
     * @return ArchiveMatcher
     */
    private function matcher(string $extension, \Closure $then): \Closure
    {
        return static fn(\SplFileInfo $info): ?Archive =>
        \str_ends_with(\strtolower($info->getFilename()), '.' . $extension) ? $then($info) : null;
    }
}
