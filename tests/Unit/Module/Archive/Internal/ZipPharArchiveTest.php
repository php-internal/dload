<?php

declare(strict_types=1);

namespace Internal\DLoad\Tests\Unit\Module\Archive\Internal;

use Testo\Codecov\Covers;
use Testo\Expect;
use Testo\Test;
use Internal\DLoad\Module\Archive\Internal\ZipPharArchive;

#[Covers(ZipPharArchive::class)]
final class ZipPharArchiveTest
{
    #[Test]
    public function constructorValidatesFile(): void
    {
        $file = $this->createMock(\SplFileInfo::class);
        $file->method('isFile')->willReturn(false);
        $file->method('getFilename')->willReturn('invalid.zip');

        Expect::exception(\InvalidArgumentException::class)->withMessage('Archive "invalid.zip" is not a file.');

        new ZipPharArchive($file);
    }
}
