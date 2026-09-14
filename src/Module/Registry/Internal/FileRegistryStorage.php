<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\Registry\Internal;

use Internal\DLoad\Module\Common\FileSystem\FS;
use Internal\DLoad\Module\Registry\Record\RepositoryRecord;
use Internal\DLoad\Module\Registry\RegistryStorage;
use Internal\DLoad\Module\Registry\RepositoryId;
use Internal\DLoad\Service\Logger;
use Internal\Path;

/**
 * Storage that keeps one JSON file per repository.
 *
 * The layout is meant to be readable and portable between machines:
 *
 * ```
 * <dir>/repositories/github/roadrunner-server/roadrunner.json
 * <dir>/repositories/gitlab/group/project.json
 * ```
 *
 * Files are written aside and renamed into place, so an interrupted or parallel run cannot
 * leave a half-written record for anyone to read.
 *
 * @internal
 * @psalm-internal Internal\DLoad
 */
final class FileRegistryStorage implements RegistryStorage
{
    private const REPOSITORIES_DIR = 'repositories';
    private const EXTENSION = '.json';

    private readonly Path $root;

    public function __construct(
        Path|string $directory,
        private readonly Logger $logger,
    ) {
        $this->root = Path::create($directory)->join(self::REPOSITORIES_DIR);
    }

    public function load(RepositoryId $id): ?RepositoryRecord
    {
        $record = $this->read($this->fileOf($id));

        // Sanitizing and case-insensitive file systems may map two identities onto one file
        return $record?->id->equals($id) === true ? $record : null;
    }

    public function save(RepositoryRecord $record): void
    {
        $file = $this->fileOf($record->id);
        $directory = $file->parent();

        $directory->isDir() or FS::mkdir($directory);

        $payload = \json_encode($record->toArray(), \JSON_THROW_ON_ERROR | \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES);

        $temp = Path::create((string) $file . '.' . (int) \getmypid() . '.tmp');
        @\file_put_contents((string) $temp, $payload) === false and throw new \RuntimeException(
            \sprintf('Failed to write registry record `%s`.', $temp),
        );

        if (!FS::moveFile($temp, $file)) {
            FS::removeFile($temp);
            throw new \RuntimeException(\sprintf('Failed to store registry record `%s`.', $file));
        }
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
            if (!$file->isFile() || !\str_ends_with($file->getFilename(), self::EXTENSION)) {
                continue;
            }

            $record = $this->read(Path::create($file->getPathname()));
            $record === null or yield $record;
        }
    }

    public function remove(RepositoryId $id): void
    {
        $file = $this->fileOf($id);
        $file->isFile() and FS::removeFile($file);
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
     * Reads a record, or returns `null` when there is none or it cannot be used.
     */
    private function read(Path $file): ?RepositoryRecord
    {
        if (!$file->isFile()) {
            return null;
        }

        try {
            $content = @\file_get_contents((string) $file);
            $content === false and throw new \RuntimeException(\sprintf('Failed to read registry record `%s`.', $file));

            /** @var mixed $payload */
            $payload = \json_decode($content, true, 512, \JSON_THROW_ON_ERROR);
            \is_array($payload) or throw new \UnexpectedValueException('Registry record must be a JSON object.');

            return RepositoryRecord::fromArray($payload);
        } catch (\Throwable $e) {
            // A half-written, hand-edited or outdated record is not worth a failed download:
            // report it and let the registry fetch the releases again.
            $this->logger->exception($e, important: false);

            return null;
        }
    }

    private function fileOf(RepositoryId $id): Path
    {
        $segments = \array_map(self::sanitize(...), [$id->type, ...\explode('/', $id->uri)]);
        $segments[\array_key_last($segments)] .= self::EXTENSION;

        return $this->root->join(...$segments);
    }
}
