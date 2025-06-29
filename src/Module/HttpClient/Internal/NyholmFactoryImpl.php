<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\HttpClient\Internal;

use Internal\DLoad\Module\HttpClient\Factory;
use Internal\DLoad\Module\HttpClient\Method;
use Nyholm\Psr7\Request;
use Nyholm\Psr7\Uri;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\UriInterface;
use Symfony\Component\HttpClient\Psr18Client;

/**
 * @internal
 */
class NyholmFactoryImpl implements Factory
{
    public function uri(string $path, array $query = []): UriInterface
    {
        // Build URI with path and query parameters
        $queryString = \http_build_query($query, '', '&', \PHP_QUERY_RFC3986);
        $uri = \sprintf('%s?%s', $path, $queryString);

        return new Uri($uri);
    }

    public function request(
        string|Method $method,
        string|UriInterface $uri,
        array $headers = [],
    ): RequestInterface {
        return new Request(Method::fromString($method)->value, $uri, $headers);
    }

    public function client(): ClientInterface
    {
        return new Psr18Client();
    }
}
