<?php

declare(strict_types=1);

namespace Internal\DLoad\Tests\Unit\Module\Archive\Internal;

use Internal\DLoad\Module\Archive\Internal\TarPharArchive;
use Testo\Codecov\Covers;
use Testo\Expect;
use Testo\Test;

#[Covers(TarPharArchive::class)]
final class TarPharArchiveTest
{
    #[Test]
    public function constructorValidatesFile(): void
    {
        $file = \Mockery::mock(\SplFileInfo::class);
        $file->allows('isFile')->andReturn(true);
        $file->allows('isReadable')->andReturn(false);
        $file->allows('getFilename')->andReturn('unreadable.tar.gz');

        Expect::exception(\InvalidArgumentException::class)->withMessage('Archive file "unreadable.tar.gz" is not readable.');

        new TarPharArchive($file);
    }
}
