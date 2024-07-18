<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\Archive;

use Closure as ArchiveMatcher;
use Internal\DLoad\Module\Archive\Internal\PharArchive;
use Internal\DLoad\Module\Archive\Internal\TarPharArchive;
use Internal\DLoad\Module\Archive\Internal\ZipPharArchive;

/**
 * @psalm-type ArchiveMatcher = \Closure(\SplFileInfo): ?Archive
 */
final class ArchiveFactory
{
    /**
     * @var array<ArchiveMatcher>
     */
    private array $matchers = [];

    /**
     * FactoryTrait constructor.
     */
    public function __construct()
    {
        $this->bootDefaultMatchers();
    }

    public function extend(\Closure $matcher): void
    {
        \array_unshift($this->matchers, $matcher);
    }

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

    private function bootDefaultMatchers(): void
    {
        $this->extend($this->matcher(
            'zip',
            static fn(\SplFileInfo $info): Archive => new ZipPharArchive($info),
        ));

        $this->extend($this->matcher(
            'tar.gz',
            static fn(\SplFileInfo $info): Archive => new TarPharArchive($info),
        ));

        $this->extend($this->matcher(
            'phar',
            static fn(\SplFileInfo $info): Archive => new PharArchive($info),
        ));
    }

    /**
     * @param string $extension
     * @param ArchiveMatcher $then
     *
     * @return ArchiveMatcher
     */
    private function matcher(string $extension, \Closure $then): \Closure
    {
        return static fn(\SplFileInfo $info): ?Archive =>
        \str_ends_with(\strtolower($info->getFilename()), '.' . $extension) ? $then($info) : null;
    }
}
