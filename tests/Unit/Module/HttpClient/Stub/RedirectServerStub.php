<?php

declare(strict_types=1);

namespace Internal\DLoad\Tests\Unit\Module\HttpClient\Stub;

use Nyholm\Psr7\Response;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * PSR-18 client that answers from a map of redirects and records every request it receives.
 *
 * A URL listed in the map answers with its redirect, any other URL with `200 OK`.
 */
final class RedirectServerStub implements ClientInterface
{
    /** @var list<RequestInterface> */
    public array $sent = [];

    /**
     * @param array<string, array{int, string}> $redirects Status and `Location` by request URL.
     */
    public function __construct(
        private readonly array $redirects = [],
    ) {}

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $this->sent[] = $request;
        $redirect = $this->redirects[(string) $request->getUri()] ?? null;

        return $redirect === null
            ? new Response(200, [], 'ok')
            : new Response($redirect[0], ['location' => $redirect[1]]);
    }

    /**
     * @return list<string>
     */
    public function urls(): array
    {
        return \array_map(static fn(RequestInterface $r): string => (string) $r->getUri(), $this->sent);
    }
}
