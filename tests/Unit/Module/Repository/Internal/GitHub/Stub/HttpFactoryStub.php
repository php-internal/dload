<?php

declare(strict_types=1);

namespace Internal\DLoad\Tests\Unit\Module\Repository\Internal\GitHub\Stub;

use Internal\DLoad\Module\HttpClient\Factory;
use Internal\DLoad\Module\HttpClient\Method;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\UriInterface;

/**
 * HTTP Factory stub for GitHub API tests.
 *
 * Provides controllable URI and request creation for testing using PHPUnit mocks.
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
     * @param callable(): MockObject $uriFactory
     * @param callable(): MockObject $requestFactory
     * @param callable(): MockObject $clientFactory
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

        /** @var MockObject&UriInterface $uri */
        $uri = ($this->uriFactory)();
        $uri->method('__toString')->willReturn("https://api.github.com{$path}");

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

        /** @var MockObject&RequestInterface $request */
        $request = ($this->requestFactory)();
        $request->method('getMethod')->willReturn($methodString);
        $request->method('getUri')->willReturn($uri);
        $request->method('getHeaders')->willReturn($headers);

        return $request;
    }

    public function client(): ClientInterface
    {
        /** @var MockObject&ClientInterface $client */
        $client = ($this->clientFactory)();

        return $client;
    }

    private function createRequestKey(string $method, UriInterface $uri): string
    {
        return $method . '|' . (string) $uri;
    }
}
