<?php

declare(strict_types=1);

namespace Internal\DLoad\Tests\Unit\Module\Archive\API;

use Internal\DLoad\Module\Archive\Exception\ArchiveException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[\Testo\Codecov\Covers(ArchiveException::class)]
final class ArchiveExceptionTest
{
    #[\Testo\Test]
    public function testExceptionInheritsFromRuntimeException(): void
    {
        // Arrange
        $exception = new ArchiveException('Test message');

        // Assert
        \Testo\Assert::instanceOf($exception, \RuntimeException::class);
    }

    #[\Testo\Test]
    public function testExceptionReturnsCorrectMessage(): void
    {
        // Arrange
        $message = 'Archive extraction failed: test reason';

        // Act
        $exception = new ArchiveException($message);

        // Assert
        \Testo\Assert::same($exception->getMessage(), $message);
    }

    #[\Testo\Test]
    public function testExceptionCanHaveCustomCode(): void
    {
        // Arrange
        $code = 123;

        // Act
        $exception = new ArchiveException('Test message', $code);

        // Assert
        \Testo\Assert::same($exception->getCode(), $code);
    }

    #[\Testo\Test]
    public function testExceptionCanHavePreviousException(): void
    {
        // Arrange
        $previous = new \Exception('Previous error');

        // Act
        $exception = new ArchiveException('Test message', 0, $previous);

        // Assert
        \Testo\Assert::same($exception->getPrevious(), $previous);
    }
}
