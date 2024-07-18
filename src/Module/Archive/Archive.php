<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\Archive;

interface Archive
{
    /**
     * Iterate archive files. If a {@see \SplFileInfo} is backed into the generator, the file will be
     * extracted to the given location.
     *
     * @return \Generator<non-empty-string, \SplFileInfo, \SplFileInfo|null, void>
     */
    public function extract(): \Generator;
}
