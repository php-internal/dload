<?php

declare(strict_types=1);

namespace Internal\DLoad\Tests\Unit\Module\Archive\Internal;

use Internal\DLoad\Module\Archive\Internal\ZipPharArchive;
use Testo\Codecov\Covers;
use Testo\Expect;
use Testo\Test;

#[Covers(ZipPharArchive::class)]
final class ZipPharArchiveTest
{
    #[Test]
    public function constructorValidatesFile(): void
    {
        $file = \Mockery::mock(\SplFileInfo::class);
        $file->allows('isFile')->andReturn(false);
        $file->allows('getFilename')->andReturn('invalid.zip');

        Expect::exception(\InvalidArgumentException::class)->withMessage('Archive "invalid.zip" is not a file.');

        new ZipPharArchive($file);
    }
}
