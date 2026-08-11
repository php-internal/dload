<?php

declare(strict_types=1);

namespace Internal\DLoad\Tests\Unit\Module\Repository\Internal\GitLab\Stub;

use Internal\DLoad\Module\HttpClient\Factory;
use Internal\DLoad\Module\HttpClient\Method;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\UriInterface;

/**
 * HTTP factory that hands out inert doubles.
 *
 * Enough to construct the GitLab repository factory, which needs a client but performs no request
 * while resolving a repository configuration.
 */
final class HttpFactoryStub implements Factory
{
    public function uri(string $path, array $query = []): UriInterface
    {
        return \Mockery::mock(UriInterface::class)->shouldIgnoreMissing();
    }

    public function request(
        string|Method $method,
        string|UriInterface $uri,
        array $headers = [],
    ): RequestInterface {
        return \Mockery::mock(RequestInterface::class)->shouldIgnoreMissing();
    }

    public function client(): ClientInterface
    {
        return \Mockery::mock(ClientInterface::class)->shouldIgnoreMissing();
    }
}
