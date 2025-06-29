<?php

declare(strict_types=1);

namespace Internal\DLoad\Tests\Unit\Module\Repository\Internal\GitHub\Stub;

use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * HTTP Client stub for GitHub API tests.
 *
 * Provides controllable responses and exceptions for testing.
 */
final class ClientStub implements ClientInterface
{
    /**
     * @var array<string, ResponseInterface>
     */
    private array $responses = [];

    /**
     * @var array<string, ClientExceptionInterface>
     */
    private array $exceptions = [];

    public function withResponse(RequestInterface $request, ResponseInterface $response): self
    {
        $clone = clone $this;
        $clone->responses[$this->createRequestKey($request)] = $response;
        return $clone;
    }

    public function withException(RequestInterface $request, ClientExceptionInterface $exception): self
    {
        $clone = clone $this;
        $clone->exceptions[$this->createRequestKey($request)] = $exception;
        return $clone;
    }

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $requestKey = $this->createRequestKey($request);

        if (isset($this->exceptions[$requestKey])) {
            throw $this->exceptions[$requestKey];
        }

        if (isset($this->responses[$requestKey])) {
            return $this->responses[$requestKey];
        }

        // Default response for tests
        return ResponseStub::ok();
    }

    private function createRequestKey(RequestInterface $request): string
    {
        return $request->getMethod() . '|' . (string) $request->getUri();
    }
}
