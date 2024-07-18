<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\Archive\Internal;

final class TarPharArchive extends PharAwareArchive
{
    protected function open(\SplFileInfo $file): \PharData
    {
        return new \PharData($file->getPathname());
    }
}
