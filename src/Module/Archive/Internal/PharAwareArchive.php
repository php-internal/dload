<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\Archive\Internal;

abstract class PharAwareArchive extends Archive
{
    protected \PharData $archive;

    public function __construct(\SplFileInfo $archive)
    {
        parent::__construct($archive);
        $this->archive = $this->open($archive);
    }

    public function extract(): \Generator
    {
        $phar = $this->archive;
        $phar->isReadable() or throw new \LogicException(
            \sprintf('Could not open "%s" for reading.', $this->archive->getPathname()),
        );

        /** @var \PharFileInfo $file */
        foreach (new \RecursiveIteratorIterator($phar) as $file) {
            /** @var \SplFileInfo|null $fileTo */
            $fileTo = yield $file->getPathname() => $file;
            $fileTo instanceof \SplFileInfo and \copy(
                $file->getPathname(),
                $fileTo->getRealPath() ?: $fileTo->getPathname(),
            );
        }
    }

    abstract protected function open(\SplFileInfo $file): \PharData;
}
