<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\HttpClient\Internal;

use Nyholm\Psr7\Stream;
use Nyholm\Psr7\Uri;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\UriInterface;

/**
 * Follows redirects and keeps credentials on the origin they were meant for.
 *
 * A credential header reaches only the scheme, host and port of the original request; any other
 * redirect target gets the request without it. The HTTP client's own redirect handling strips just
 * `Authorization` and `Cookie`, so a custom header such as GitLab's `PRIVATE-TOKEN` would follow
 * a release link to a host the project maintainer picked.
 *
 * @internal
 */
final class RedirectingClient implements ClientInterface
{
    private const REDIRECTS = [301, 302, 303, 307, 308];

    /** Lower case names of the headers that carry credentials. */
    private const CREDENTIALS = ['authorization', 'private-token', 'job-token', 'cookie'];

    /**
     * @param ClientInterface $client Must not follow redirects by itself.
     * @param int<0, max> $maxRedirects
     */
    public function __construct(
        private readonly ClientInterface $client,
        private readonly int $maxRedirects = 20,
    ) {}

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $origin = self::origin($request->getUri());

        for ($redirects = 0; ; ++$redirects) {
            $response = $this->client->sendRequest($request);
            $status = $response->getStatusCode();
            $location = $response->getHeaderLine('location');

            if ($location === '' || !\in_array($status, self::REDIRECTS, true) || $redirects >= $this->maxRedirects) {
                return $response;
            }

            $uri = self::resolve($request->getUri(), $location);
            $request = $request->withUri($uri);

            // Like curl and browsers: 303 always turns into GET, 301 and 302 only turn a POST into GET.
            $method = $request->getMethod();
            if ($method !== 'HEAD' && ($status === 303 || ($method === 'POST' && $status !== 307 && $status !== 308))) {
                $request = $request->withMethod('GET')
                    ->withBody(Stream::create(''))
                    ->withoutHeader('content-type')
                    ->withoutHeader('content-length')
                    ->withoutHeader('transfer-encoding');
            }

            if (self::origin($uri) !== $origin) {
                foreach (self::CREDENTIALS as $header) {
                    $request = $request->withoutHeader($header);
                }
            }
        }
    }

    /**
     * @return string `scheme://host:port` with the default port spelled out.
     */
    private static function origin(UriInterface $uri): string
    {
        $scheme = \strtolower($uri->getScheme());
        $port = (string) ($uri->getPort() ?? ['http' => 80, 'https' => 443][$scheme] ?? '');

        return $scheme . '://' . \strtolower($uri->getHost()) . ':' . $port;
    }

    /**
     * Resolves a `Location` value against the URI that answered with it.
     */
    private static function resolve(UriInterface $base, string $location): UriInterface
    {
        $target = new Uri($location);
        if ($target->getScheme() !== '') {
            return $target;
        }

        if ($target->getAuthority() !== '') {
            return $target->withScheme($base->getScheme());
        }

        $path = $target->getPath();
        $query = $target->getQuery();
        if ($path === '') {
            $path = $base->getPath();
            $query === '' and $query = $base->getQuery();
        } elseif ($path[0] !== '/') {
            $basePath = $base->getPath() === '' ? '/' : $base->getPath();
            $path = \substr($basePath, 0, (int) \strrpos($basePath, '/') + 1) . $path;
        }

        return $base->withPath(self::removeDotSegments($path))
            ->withQuery($query)
            ->withFragment($target->getFragment());
    }

    private static function removeDotSegments(string $path): string
    {
        $output = [];
        foreach (\explode('/', $path) as $segment) {
            match ($segment) {
                '.' => null,
                '..' => \count($output) > 1 ? \array_pop($output) : null,
                default => $output[] = $segment,
            };
        }

        $last = \substr($path, -3) === '/..' || \substr($path, -2) === '/.' || $path === '.' || $path === '..';
        $last and $output[] = '';

        return \implode('/', $output);
    }
}
