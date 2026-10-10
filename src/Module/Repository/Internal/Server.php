<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\Repository\Internal;

use Psr\Http\Message\UriInterface;

/**
 * Address of a repository hosting instance: scheme, host and port, no path.
 *
 * Lets a repository point at GitHub Enterprise Server, a self-hosted GitLab or a local fake API
 * instead of the public host. The value is normalized, so equal addresses compare equal:
 * lower case, default port dropped.
 *
 * ```php
 * $server = Server::fromString('GHE.example.com:8443');
 * echo $server->url();           // https://ghe.example.com:8443
 * echo $server->tokenVariable(); // DLOAD_TOKEN_GHE_EXAMPLE_COM_8443
 * ```
 *
 * @internal
 */
final class Server implements \Stringable
{
    private const DEFAULT_PORTS = ['http' => 80, 'https' => 443];

    /**
     * @param 'http'|'https' $scheme
     * @param non-empty-string $host Lower case; an IPv6 address keeps its brackets.
     * @param int<1, 65535>|null $port Null for the default port of the scheme.
     */
    private function __construct(
        public readonly string $scheme,
        public readonly string $host,
        public readonly ?int $port,
    ) {}

    /**
     * @param string $value `[scheme://]host[:port]`; the scheme defaults to `https`.
     * @throws \InvalidArgumentException When the value is not a bare server address.
     */
    public static function fromString(string $value): self
    {
        $value = \trim($value);
        $url = \str_contains($value, '://') ? $value : 'https://' . $value;
        $parts = \parse_url($url);

        $fail = static fn(string $reason): \InvalidArgumentException => new \InvalidArgumentException(
            \sprintf('Invalid server `%s`: %s Expected `[scheme://]host[:port]`.', $value, $reason),
        );

        \is_array($parts) && isset($parts['host']) or throw $fail('no host.');
        $scheme = \strtolower($parts['scheme'] ?? '');
        $scheme === 'http' || $scheme === 'https' or throw $fail('only `http` and `https` are supported.');
        isset($parts['user']) || isset($parts['pass']) and throw $fail('credentials are not allowed.');
        \in_array($parts['path'] ?? '', ['', '/'], true) or throw $fail('a path is not allowed.');
        isset($parts['query']) || isset($parts['fragment']) and throw $fail('a query or fragment is not allowed.');

        $host = \strtolower($parts['host']);
        \preg_match('/^(?:\[[0-9a-f:.]+\]|[a-z0-9]([a-z0-9.-]*[a-z0-9])?)$/', $host) === 1 or throw $fail('the host is malformed.');

        $port = $parts['port'] ?? null;
        if ($port !== null && $port < 1) {
            throw $fail('the port is out of range.');
        }
        $port === self::DEFAULT_PORTS[$scheme] and $port = null;

        /**
         * `parse_url()` rejects a port above 65535
         * @var non-empty-string $host
         * @var int<1, 65535>|null $port
         */
        return new self($scheme, $host, $port);
    }

    /**
     * Parses a configured server, treating an empty value and the public host alike.
     *
     * @param non-empty-string $publicHost URL of the public host of the repository type.
     * @return self|null Null for the public host.
     * @throws \InvalidArgumentException When the value is not a bare server address.
     */
    public static function selfHosted(?string $value, string $publicHost): ?self
    {
        if ($value === null || \trim($value) === '') {
            return null;
        }

        $server = self::fromString($value);

        return $server->equals(self::fromString($publicHost)) ? null : $server;
    }

    /**
     * @return array{string, string, int|null} Lower case scheme and host, and the explicit port.
     */
    public static function split(string|UriInterface $uri): array
    {
        if ($uri instanceof UriInterface) {
            return [\strtolower($uri->getScheme()), \strtolower($uri->getHost()), $uri->getPort()];
        }

        $parts = \parse_url($uri);

        return \is_array($parts)
            ? [\strtolower($parts['scheme'] ?? ''), \strtolower($parts['host'] ?? ''), $parts['port'] ?? null]
            : ['', '', null];
    }

    /**
     * @return non-empty-string `host[:port]`.
     */
    public function authority(): string
    {
        return $this->port === null ? $this->host : $this->host . ':' . $this->port;
    }

    /**
     * @return non-empty-string `scheme://host[:port]`, no trailing slash.
     */
    public function url(): string
    {
        return $this->scheme . '://' . $this->authority();
    }

    /**
     * Name of the environment variable that holds the API token for this server.
     *
     * The name is derived from the address so that only the user's environment, never a config
     * file, decides which host receives a token.
     *
     * @return non-empty-string
     */
    public function tokenVariable(): string
    {
        return 'DLOAD_TOKEN_' . \trim((string) \preg_replace('/[^A-Z0-9]+/', '_', \strtoupper($this->authority())), '_');
    }

    public function isLoopback(): bool
    {
        return $this->host === 'localhost'
            || $this->host === '[::1]'
            || \str_ends_with($this->host, '.localhost')
            || \preg_match('/^127\.\d{1,3}\.\d{1,3}\.\d{1,3}$/', $this->host) === 1;
    }

    /**
     * Whether a token may travel to this server: plain HTTP would expose it on the wire, unless
     * the traffic never leaves the machine.
     */
    public function isSecure(): bool
    {
        return $this->scheme === 'https' || $this->isLoopback();
    }

    /**
     * Whether the URI points exactly at this server: same scheme, host and port, no subdomains.
     */
    public function serves(string|UriInterface $uri): bool
    {
        [$scheme, $host, $port] = self::split($uri);
        $port ??= self::DEFAULT_PORTS[$scheme] ?? null;

        return $scheme === $this->scheme
            && $host === $this->host
            && $port === ($this->port ?? self::DEFAULT_PORTS[$this->scheme]);
    }

    public function equals(self $other): bool
    {
        return $this->url() === $other->url();
    }

    public function __toString(): string
    {
        return $this->url();
    }
}
