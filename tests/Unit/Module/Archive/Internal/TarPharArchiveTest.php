<?php

declare(strict_types=1);

namespace Internal\DLoad\Tests\Unit\Module\Archive\Internal;

use Internal\DLoad\Module\Archive\Internal\TarPharArchive;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[\Testo\Codecov\Covers(TarPharArchive::class)]
final class TarPharArchiveTest
{
    #[\Testo\Test]
    public function testConstructorValidatesFile(): void
    {
        // Arrange
        $file = $this->createMock(\SplFileInfo::class);
        $file->method('isFile')->willReturn(true);
        $file->method('isReadable')->willReturn(false);
        $file->method('getFilename')->willReturn('unreadable.tar.gz');

        // Assert
        \Testo\Expect::exception(\InvalidArgumentException::class)->withMessage('Archive file "unreadable.tar.gz" is not readable.');

        // Act
        new TarPharArchive($file);
    }
}
