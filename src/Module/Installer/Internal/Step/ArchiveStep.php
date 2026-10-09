<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\Installer\Internal\Step;

use Internal\DLoad\Module\Archive\ArchiveEntryPath;
use Internal\DLoad\Module\Archive\ArchiveFactory;
use Internal\DLoad\Module\Binary\BinaryProvider;
use Internal\DLoad\Module\Common\DloadResult;
use Internal\DLoad\Module\Common\FileSystem\FS;
use Internal\DLoad\Module\Config\Schema\Action\Type;
use Internal\DLoad\Module\Config\Schema\Embed\File;
use Internal\DLoad\Module\Downloader\Exception\NothingExtracted;
use Internal\DLoad\Module\Installer\Installation;
use Internal\DLoad\Module\Installer\Internal\InstallStep;
use Internal\DLoad\Service\Logger;
use Internal\Path;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Installs an archive action: extracts the whole archive into the destination, preserving the
 * internal directory structure.
 *
 * Unlike the flat extraction used for single-binary tools, this keeps relative paths intact,
 * which is required for archives whose files reference each other by relative path
 * (e.g. a binary resolving a shared library via an `$ORIGIN/../lib` rpath).
 *
 * When a single top-level directory wraps the whole archive, it is stripped (like
 * `tar --strip-components=1`). When `<file>` rules are defined, they act as an include filter
 * (matched by file name); otherwise every entry is extracted. A configured `<binary>` is only
 * used to locate the executable inside the extracted tree for version checks — it is not moved.
 *
 * @internal
 * @psalm-internal Internal\DLoad\Module\Installer
 */
final class ArchiveStep implements InstallStep
{
    public function __construct(
        private readonly Logger $logger,
        private readonly OutputInterface $output,
        private readonly ArchiveFactory $archiveFactory,
        private readonly BinaryProvider $binaryProvider,
    ) {}

    /**
     * @throws NothingExtracted When the archive turned out to be empty or nothing matched the filters
     */
    public function install(Installation $installation, callable $next): DloadResult
    {
        if ($installation->type !== Type::Archive) {
            return $next($installation);
        }

        $software = $installation->software;
        $destination = $installation->destination;
        $fileInfo = $installation->download->file;
        $archive = $this->archiveFactory->create($fileInfo);
        $this->logger->info('Extracting %s (preserving structure)', $fileInfo->getFilename());

        # List entry paths (without extracting) to detect a single wrapping directory to strip
        $entries = $archive->entries();
        $stripPrefix = ArchiveEntryPath::commonTopLevelDirectory($entries);

        $binaryRule = $installation->binaryRule;
        $hasFilters = $software->files !== [];

        $resultFiles = [];
        $resultBinary = null;

        # Extract entries to their relative destinations
        $extractor = $archive->extract();
        while ($extractor->valid()) {
            $relativePath = $extractor->key();
            $file = $extractor->current();
            \assert($file instanceof \SplFileInfo);

            $target = self::resolveTarget($relativePath, $stripPrefix, $destination);
            if ($target === null) {
                $this->logger->debug('Skipping archive entry `%s`.', $relativePath);
                $extractor->next();
                continue;
            }

            $isBinary = $binaryRule !== null && \preg_match($binaryRule->pattern, $file->getFilename()) === 1;
            $matchedFilter = $hasFilters ? self::matchFileRule($file, $software->files) : null;

            # With explicit <file> rules, extract only matching entries (the binary is always kept)
            if ($hasFilters && $matchedFilter === null && !$isBinary) {
                $this->logger->debug('Skipping file `%s`.', $relativePath);
                $extractor->next();
                continue;
            }

            $this->logger->debug('Extracting %s to %s...', $relativePath, (string) $target);
            FS::mkdir($target->parent());
            # `send()` performs the extraction and already advances the generator to the next entry,
            # so this branch must not call `next()` afterwards.
            $extractor->send(new \SplFileInfo((string) $target));

            # Binaries get the executable bit; files honor their configured chmod
            $chmod = $isBinary ? 0o755 : $matchedFilter?->chmod;
            $chmod === null or @\chmod((string) $target, $chmod);

            $resultFiles[] = $target;

            if ($isBinary && $resultBinary === null && $software->binary !== null) {
                # Locate the binary inside the extracted tree for version checks; keep it in place
                $resultBinary = $this->binaryProvider->getLocalBinary($target->parent(), $software->binary);
            }
        }

        $resultFiles === [] and throw new NothingExtracted(
            assetName: $fileInfo->getFilename(),
            rules: $installation->describeRules(),
            files: $entries,
        );

        $this->output->writeln(
            \sprintf(
                '<comment>%d</comment> file(s) from <comment>%s</comment> have been installed into <info>%s</info>',
                \count($resultFiles),
                $installation->download->version,
                (string) $destination,
            ),
        );

        return new DloadResult($resultFiles, $resultBinary);
    }

    /**
     * Resolves the extraction target for an archive entry, stripping the common wrapping directory
     * and guarding against path traversal (zip-slip).
     *
     * @param non-empty-string $relativePath Entry path relative to the archive root (forward slashes)
     * @param string $stripPrefix Leading directory to remove (e.g. `package-1.0/`), or an empty string
     * @return Path|null Target path, or null when the entry should be skipped
     */
    private static function resolveTarget(string $relativePath, string $stripPrefix, Path $destination): ?Path
    {
        $relative = ArchiveEntryPath::relative($relativePath, $stripPrefix);

        return $relative === null ? null : $destination->join($relative);
    }

    /**
     * Finds the first `<file>` rule whose pattern matches the given archive entry by its file name.
     *
     * @param list<File> $filters File mapping configurations
     */
    private static function matchFileRule(\SplFileInfo $file, array $filters): ?File
    {
        foreach ($filters as $filter) {
            if (\preg_match($filter->pattern, $file->getFilename()) === 1) {
                return $filter;
            }
        }

        return null;
    }
}
