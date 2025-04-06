<?php

declare(strict_types=1);

namespace Internal\DLoad\Tests\Unit\Module\Archive\Internal;

use Internal\DLoad\Module\Archive\Internal\ZipPharArchive;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ZipPharArchive::class)]
final class ZipPharArchiveTest extends TestCase
{
    public function testConstructorValidatesFile(): void
    {
        // Arrange
        $file = $this->createMock(\SplFileInfo::class);
        $file->method('isFile')->willReturn(false);
        $file->method('getFilename')->willReturn('invalid.zip');

        // Assert
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Archive "invalid.zip" is not a file.');

        // Act
        new ZipPharArchive($file);
    }
}
