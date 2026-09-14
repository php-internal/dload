<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\Registry\Internal;

use Internal\DLoad\Module\Common\FileSystem\FS;
use Internal\DLoad\Module\Registry\Record\ReleaseRecord;
use Internal\DLoad\Module\Registry\Record\RepositoryRecord;
use Internal\DLoad\Module\Registry\RegistryStorage;
use Internal\DLoad\Module\Registry\RepositoryId;
use Internal\DLoad\Service\Logger;
use Internal\Path;

/**
 * Storage that keeps one directory per repository.
 *
 * The layout is meant to be readable and portable between machines: the index holds the
 * metadata and the tags of every segment, and each segment of releases is a file of its own,
 * so a run reads only the segments it iterates.
 *
 * ```
 * <dir>/repositories/github/roadrunner-server/roadrunner/index.json
 * <dir>/repositories/github/roadrunner-server/roadrunner/releases-0001.json
 * <dir>/repositories/gitlab/group/project/index.json
 * ```
 *
 * Files are written aside and renamed into place, so an interrupted or parallel run cannot
 * leave a half-written file for anyone to read. Segments are written before the index, so the
 * index never points at a file that is not there yet.
 *
 * @internal
 * @psalm-internal Internal\DLoad
 */
final class FileRegistryStorage implements RegistryStorage
{
    private const REPOSITORIES_DIR = 'repositories';
    private const INDEX_FILE = 'index.json';
    private const SEGMENT_PREFIX = 'releases-';
    private const EXTENSION = '.json';
    private const TEMP_EXTENSION = '.tmp';

    /** Age after which a leftover temporary file of a crashed run is removed, in seconds. */
    private const STALE_TEMP_AGE = 3600;

    private readonly Path $root;

    public function __construct(
        Path $directory,
        private readonly Logger $logger,
    ) {
        $this->root = $directory->join(self::REPOSITORIES_DIR);
    }

    public function load(RepositoryId $id): ?RepositoryRecord
    {
        $record = $this->readIndex($this->directoryOf($id));

        // Sanitizing and case-insensitive file systems may map two identities onto one directory
        return $record?->id->equals($id) === true ? $record : null;
    }

    public function save(RepositoryRecord $record): void
    {
        $directory = $this->directoryOf($record->id);
        $directory->isDir() or FS::mkdir($directory);
        $directory->isDir() or throw new \RuntimeException(\sprintf('Failed to create registry directory `%s`.', $directory));

        foreach ($record->segments as $segment) {
            $segment->dirty and $this->write(
                $this->segmentFile($directory, $segment->key),
                \array_map(static fn(ReleaseRecord $release): array => $release->toArray(), $segment->releases()),
            );
        }

        $this->write($directory->join(self::INDEX_FILE), $record->toArray());

        $this->removeOrphans($directory, $record);
    }

    public function all(): iterable
    {
        if (!$this->root->isDir()) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator((string) $this->root, \FilesystemIterator::SKIP_DOTS),
        );

        /** @var \SplFileInfo $file */
        foreach ($iterator as $file) {
            if (!$file->isFile() || $file->getFilename() !== self::INDEX_FILE) {
                continue;
            }

            $record = $this->readIndex(Path::create($file->getPath()));
            $record === null or yield $record;
        }
    }

    public function remove(RepositoryId $id): void
    {
        $directory = $this->directoryOf($id);
        if (!$directory->isDir()) {
            return;
        }

        FS::removeDir($directory);

        // Owner and type directories are worth nothing once empty
        for ($parent = $directory->parent(); (string) $parent !== (string) $this->root && $this->isEmptyDir($parent); $parent = $parent->parent()) {
            FS::removeDir($parent);
        }
    }

    public function clear(): void
    {
        $this->root->isDir() and FS::removeDir($this->root);
    }

    /**
     * Keeps a path segment safe for every file system: anything but plain ASCII is replaced,
     * a segment that would otherwise be empty or a directory reference gets a placeholder, and
     * a Windows device name (`nul`, `con`, `com1`...) gets a prefix, as Windows opens the device
     * whatever the extension.
     *
     * @return non-empty-string
     */
    private static function sanitize(string $segment): string
    {
        $safe = (string) \preg_replace('/[^A-Za-z0-9._-]+/', '_', $segment);

        if ($safe === '' || \trim($safe, '.') === '') {
            return '_';
        }

        return \preg_match('/^(?:con|prn|aux|nul|com[0-9]|lpt[0-9])(?:\.|$)/i', $safe) === 1 ? '_' . $safe : $safe;
    }

    /**
     * Reads the index of a record, or returns `null` when there is none or it cannot be used.
     */
    private function readIndex(Path $directory): ?RepositoryRecord
    {
        $file = $directory->join(self::INDEX_FILE);
        if (!$file->isFile()) {
            return null;
        }

        try {
            $record = RepositoryRecord::fromArray(
                $this->decode($file),
                fn(string $key): array => $this->readSegment($directory, $key),
            );

            // A segment file missing now would fail the run when its turn comes
            foreach ($record->segments as $segment) {
                $this->segmentFile($directory, $segment->key)->isFile() or throw new \RuntimeException(
                    \sprintf('Registry record `%s` refers to a missing segment `%s`.', $directory, $segment->key),
                );
            }

            return $record;
        } catch (\Throwable $e) {
            // A half-written, hand-edited or outdated record is not worth a failed download:
            // report it and let the registry fetch the releases again.
            $this->logger->exception($e, important: false);

            return null;
        }
    }

    /**
     * @param non-empty-string $key
     * @return list<ReleaseRecord>
     * @throws \RuntimeException When the segment cannot be read; the record is dropped, so the next run starts afresh.
     */
    private function readSegment(Path $directory, string $key): array
    {
        try {
            $releases = [];
            /** @var mixed $item */
            foreach ($this->decode($this->segmentFile($directory, $key)) as $item) {
                \is_array($item) or throw new \UnexpectedValueException('Registry segment must hold release objects.');
                $releases[] = ReleaseRecord::fromArray($item);
            }

            return $releases;
        } catch (\Throwable $e) {
            $directory->isDir() and FS::removeDir($directory);

            throw new \RuntimeException(
                \sprintf('Registry segment `%s` of `%s` is unreadable; the record was dropped.', $key, $directory),
                previous: $e,
            );
        }
    }

    /**
     * @return array<array-key, mixed>
     * @throws \RuntimeException
     * @throws \JsonException
     */
    private function decode(Path $file): array
    {
        $content = @\file_get_contents((string) $file);
        $content === false and throw new \RuntimeException(\sprintf('Failed to read registry file `%s`.', $file));

        /** @var mixed $payload */
        $payload = \json_decode($content, true, 512, \JSON_THROW_ON_ERROR);
        \is_array($payload) or throw new \UnexpectedValueException(\sprintf('Registry file `%s` must hold a JSON structure.', $file));

        return $payload;
    }

    /**
     * Writes the payload aside and renames it into place.
     *
     * @throws \RuntimeException When the file cannot be written.
     */
    private function write(Path $file, array $payload): void
    {
        $json = \json_encode($payload, \JSON_THROW_ON_ERROR | \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES);

        $temp = Path::create((string) $file . '.' . (int) \getmypid() . self::TEMP_EXTENSION);
        @\file_put_contents((string) $temp, $json) === false and throw new \RuntimeException(
            \sprintf('Failed to write registry file `%s`.', $temp),
        );

        if (!FS::moveFile($temp, $file)) {
            FS::removeFile($temp);
            throw new \RuntimeException(\sprintf('Failed to store registry file `%s`.', $file));
        }
    }

    /**
     * Removes segment files the index no longer refers to and temporary files a crashed run left behind.
     */
    private function removeOrphans(Path $directory, RepositoryRecord $record): void
    {
        $known = [self::INDEX_FILE];
        foreach ($record->segments as $segment) {
            $known[] = self::SEGMENT_PREFIX . self::sanitize($segment->key) . self::EXTENSION;
        }

        foreach (new \FilesystemIterator((string) $directory, \FilesystemIterator::SKIP_DOTS) as $file) {
            /** @var \SplFileInfo $file */
            $name = $file->getFilename();
            $stale = \str_ends_with($name, self::TEMP_EXTENSION)
                ? \time() - $file->getMTime() > self::STALE_TEMP_AGE
                : !\in_array($name, $known, true);

            $stale && $file->isFile() and FS::removeFile(Path::create($file->getPathname()));
        }
    }

    private function isEmptyDir(Path $directory): bool
    {
        return $directory->isDir() && !(new \FilesystemIterator((string) $directory, \FilesystemIterator::SKIP_DOTS))->valid();
    }

    private function directoryOf(RepositoryId $id): Path
    {
        return $this->root->join(...\array_map(self::sanitize(...), [$id->type, ...\explode('/', $id->uri)]));
    }

    /**
     * @param non-empty-string $key
     */
    private function segmentFile(Path $directory, string $key): Path
    {
        return $directory->join(self::SEGMENT_PREFIX . self::sanitize($key) . self::EXTENSION);
    }
}
