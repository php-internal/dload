<?php

declare(strict_types=1);

namespace Internal\DLoad\Tests\Unit\Module\Installer\Stub;

use Internal\DLoad\Module\Archive\Archive;

/**
 * Archive with given entries, including the ones a real archive library would refuse to write.
 */
final class ListedArchive implements Archive
{
    /**
     * @param array<non-empty-string, string> $entries Content by entry path.
     */
    public function __construct(
        private readonly array $entries,
    ) {}

    public function entries(): array
    {
        return \array_keys($this->entries);
    }

    public function extract(): \Generator
    {
        foreach ($this->entries as $path => $content) {
            $to = yield $path => new \SplFileInfo(\basename($path));
            $to instanceof \SplFileInfo and \file_put_contents($to->getPathname(), $content);
        }
    }
}
