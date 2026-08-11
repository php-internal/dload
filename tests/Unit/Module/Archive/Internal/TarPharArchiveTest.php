<?php

declare(strict_types=1);

namespace Internal\DLoad\Tests\Unit\Module\Archive\Internal;

use Testo\Codecov\Covers;
use Testo\Expect;
use Testo\Test;
use Internal\DLoad\Module\Archive\Internal\TarPharArchive;

#[Covers(TarPharArchive::class)]
final class TarPharArchiveTest
{
    #[Test]
    public function constructorValidatesFile(): void
    {
        $file = $this->createMock(\SplFileInfo::class);
        $file->method('isFile')->willReturn(true);
        $file->method('isReadable')->willReturn(false);
        $file->method('getFilename')->willReturn('unreadable.tar.gz');

        Expect::exception(\InvalidArgumentException::class)->withMessage('Archive file "unreadable.tar.gz" is not readable.');

        new TarPharArchive($file);
    }
}
