<?php

declare(strict_types=1);

namespace Internal\DLoad\Tests\Unit\Module\Repository\Stub;

use Internal\DLoad\Module\HttpClient\Factory;
use Internal\DLoad\Module\HttpClient\Internal\NyholmFactoryImpl;
use Internal\DLoad\Module\HttpClient\Method;
use Internal\DLoad\Service\Logger;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\UriInterface;

/**
 * HTTP factory that builds real requests and records every request its clients send.
 *
 * Each request is answered with an empty JSON list, which reads as a repository without releases.
 */
final class RecordingHttpFactory implements Factory, ClientInterface
{
    /** @var list<RequestInterface> */
    public array $sent = [];

    private readonly NyholmFactoryImpl $factory;

    public function __construct()
    {
        $this->factory = new NyholmFactoryImpl(new Logger());
    }

    public function uri(string $path, array $query = []): UriInterface
    {
        return $this->factory->uri($path, $query);
    }

    public function request(string|Method $method, string|UriInterface $uri, array $headers = []): RequestInterface
    {
        return $this->factory->request($method, $uri, $headers);
    }

    public function client(): ClientInterface
    {
        return $this;
    }

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $this->sent[] = $request;

        return ResponseStub::ok('[]');
    }
}
