<?php

declare(strict_types=1);

namespace Internal\DLoad\Tests\Unit\Module\Archive\API;

use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Test;
use Internal\DLoad\Module\Archive\Exception\ArchiveException;

#[Covers(ArchiveException::class)]
final class ArchiveExceptionTest
{
    #[Test]
    public function exceptionInheritsFromRuntimeException(): void
    {
        $exception = new ArchiveException('Test message');

        Assert::instanceOf($exception, \RuntimeException::class);
    }

    #[Test]
    public function exceptionReturnsCorrectMessage(): void
    {
        $message = 'Archive extraction failed: test reason';

        $exception = new ArchiveException($message);

        Assert::same($exception->getMessage(), $message);
    }

    #[Test]
    public function exceptionCanHaveCustomCode(): void
    {
        $code = 123;

        $exception = new ArchiveException('Test message', $code);

        Assert::same($exception->getCode(), $code);
    }

    #[Test]
    public function exceptionCanHavePreviousException(): void
    {
        $previous = new \Exception('Previous error');

        $exception = new ArchiveException('Test message', 0, $previous);

        Assert::same($exception->getPrevious(), $previous);
    }
}
