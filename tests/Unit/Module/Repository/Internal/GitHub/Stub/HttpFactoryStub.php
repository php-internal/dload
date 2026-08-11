<?php

declare(strict_types=1);

namespace Internal\DLoad\Tests\Unit\Module\Repository\Internal\GitHub\Stub;

use Internal\DLoad\Module\HttpClient\Factory;
use Internal\DLoad\Module\HttpClient\Method;
use Mockery\MockInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\UriInterface;

/**
 * HTTP Factory stub for GitHub API tests.
 *
 * Provides controllable URI and request creation for testing using Mockery mocks.
 */
final class HttpFactoryStub implements Factory
{
    /**
     * @var array<string, UriInterface>
     */
    private array $predefinedUris = [];

    /**
     * @var array<string, RequestInterface>
     */
    private array $predefinedRequests = [];

    /**
     * @param callable(): (UriInterface&MockInterface) $uriFactory
     * @param callable(): (RequestInterface&MockInterface) $requestFactory
     * @param callable(): (ClientInterface&MockInterface) $clientFactory
     */
    public function __construct(
        private readonly mixed $uriFactory,
        private readonly mixed $requestFactory,
        private readonly mixed $clientFactory,
    ) {}

    public function withUri(string $path, UriInterface $uri): self
    {
        $clone = clone $this;
        $clone->predefinedUris[$path] = $uri;
        return $clone;
    }

    public function withRequest(string $method, UriInterface $uri, RequestInterface $request): self
    {
        $clone = clone $this;
        $clone->predefinedRequests[$this->createRequestKey($method, $uri)] = $request;
        return $clone;
    }

    public function uri(string $path, array $query = []): UriInterface
    {
        if (isset($this->predefinedUris[$path])) {
            return $this->predefinedUris[$path];
        }

        /** @var UriInterface&MockInterface $uri */
        $uri = ($this->uriFactory)();
        $uri->allows('__toString')->andReturn("https://api.github.com{$path}");

        return $uri;
    }

    public function request(
        string|Method $method,
        string|UriInterface $uri,
        array $headers = [],
    ): RequestInterface {
        $methodString = $method instanceof Method ? $method->value : $method;
        $requestKey = $this->createRequestKey($methodString, $uri);

        if (isset($this->predefinedRequests[$requestKey])) {
            return $this->predefinedRequests[$requestKey];
        }

        /** @var RequestInterface&MockInterface $request */
        $request = ($this->requestFactory)();
        $request->allows('getMethod')->andReturn($methodString);
        $request->allows('getUri')->andReturn($uri);
        $request->allows('getHeaders')->andReturn($headers);

        return $request;
    }

    public function client(): ClientInterface
    {
        /** @var ClientInterface&MockInterface $client */
        $client = ($this->clientFactory)();

        return $client;
    }

    private function createRequestKey(string $method, UriInterface $uri): string
    {
        return $method . '|' . (string) $uri;
    }
}
