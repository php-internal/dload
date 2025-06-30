<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\HttpClient;

use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\UriInterface;

/**
 * Factory class for creating HTTP client components.
 *
 * This class provides methods to create URIs, requests, and a PSR-18 compliant HTTP client.
 * It is designed to be used in the context of the DLoad application for making HTTP requests.
 *
 * @internal
 */
interface Factory
{
    /**
     * Creates a URI with the specified path and query parameters.
     *
     * @param non-empty-string $path The path to append to the base URI.
     * @param array $query Associative array of query parameters.
     * @return UriInterface The constructed URI.
     */
    public function uri(string $path, array $query = []): UriInterface;

    /**
     * Creates a new HTTP request with the specified method, URI, and headers.
     *
     * @param string|Method $method The HTTP method (e.g., 'GET', 'POST').
     * @param string|UriInterface $uri The request URI.
     * @param array $headers Associative array of headers.
     * @return RequestInterface The constructed request.
     */
    public function request(
        string|Method $method,
        string|UriInterface $uri,
        array $headers = [],
    ): RequestInterface;

    /**
     * Creates a new PSR-18 HTTP client instance.
     *
     * @return ClientInterface The PSR-18 compliant HTTP client.
     */
    public function client(): ClientInterface;
}
