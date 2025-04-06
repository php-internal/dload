<?php

declare(strict_types=1);

namespace Internal\DLoad\Tests\Unit\Module\Archive\API;

use Internal\DLoad\Module\Archive\Exception\ArchiveException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ArchiveException::class)]
final class ArchiveExceptionTest extends TestCase
{
    public function testExceptionInheritsFromRuntimeException(): void
    {
        // Arrange
        $exception = new ArchiveException('Test message');

        // Assert
        self::assertInstanceOf(\RuntimeException::class, $exception);
    }

    public function testExceptionReturnsCorrectMessage(): void
    {
        // Arrange
        $message = 'Archive extraction failed: test reason';

        // Act
        $exception = new ArchiveException($message);

        // Assert
        self::assertSame($message, $exception->getMessage());
    }

    public function testExceptionCanHaveCustomCode(): void
    {
        // Arrange
        $code = 123;

        // Act
        $exception = new ArchiveException('Test message', $code);

        // Assert
        self::assertSame($code, $exception->getCode());
    }

    public function testExceptionCanHavePreviousException(): void
    {
        // Arrange
        $previous = new \Exception('Previous error');

        // Act
        $exception = new ArchiveException('Test message', 0, $previous);

        // Assert
        self::assertSame($previous, $exception->getPrevious());
    }
}
