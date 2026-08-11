<?php

declare(strict_types=1);

namespace Internal\DLoad\Tests\Unit\Module\Repository\Internal\GitHub\Stub;

use Psr\Http\Client\ClientExceptionInterface;

/**
 * A real PSR-18 client exception to hand to the HTTP client stub.
 *
 * Not a Mockery mock on purpose: Mockery cannot generate a mock for a `Throwable` descendant —
 * the interface may not be implemented directly, and `Exception::getMessage()` is `final`.
 */
final class ClientExceptionStub extends \RuntimeException implements ClientExceptionInterface {}
