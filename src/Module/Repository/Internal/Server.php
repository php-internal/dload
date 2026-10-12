<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\Repository\Internal;

use Psr\Http\Message\UriInterface;

/**
 * Address of a repository hosting instance: scheme, host and port, no path.
 *
 * Lets a repository point at GitHub Enterprise Server, a self-hosted GitLab or a local fake API
 * instead of the public host; {@see RepositoryLocation} takes it from the repository URL. The value is normalized, so equal addresses compare equal:
 * lower case, default port dropped.
 *
 * ```php
 * $server = Server::fromString('GHE.example.com:8443');
 * echo $server->url();           // https://ghe.example.com:8443
 * echo $server->tokenVariable(); // DLOAD_TOKEN_GHE_EXAMPLE_COM___8443
 * ```
 *
 * @internal
 */
final class Server implements \Stringable
{
    private const DEFAULT_PORTS = ['http' => 80, 'https' => 443];

    /**
     * Dot-separated labels of letters, digits and inner hyphens. {@see self::tokenVariable()} relies
     * on a label never starting or ending with a hyphen.
     */
    private const HOSTNAME = '/^[a-z0-9](?:[a-z0-9-]*[a-z0-9])?(?:\.[a-z0-9](?:[a-z0-9-]*[a-z0-9])?)*$/';

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
            \sprintf('Invalid server `%s`: %s', $value, $reason),
        );

        \is_array($parts) && isset($parts['host']) or throw $fail('no host.');
        $scheme = \strtolower($parts['scheme'] ?? '');
        $scheme === 'http' || $scheme === 'https' or throw $fail('only `http` and `https` are supported.');
        isset($parts['user']) || isset($parts['pass']) and throw $fail('credentials are not allowed.');
        \in_array($parts['path'] ?? '', ['', '/'], true) or throw $fail('a path is not allowed.');
        isset($parts['query']) || isset($parts['fragment']) and throw $fail('a query or fragment is not allowed.');

        $host = \strtolower($parts['host']);
        $valid = \str_starts_with($host, '[')
            ? \filter_var(\substr($host, 1, -1), \FILTER_VALIDATE_IP, \FILTER_FLAG_IPV6) !== false
            : \preg_match(self::HOSTNAME, $host) === 1;
        $valid or throw $fail('the host is malformed.');

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
     * file, decides which host receives a token. Distinct servers must never share a name, or a
     * config naming a lookalike host would receive the token of another one:
     *
     * - the host is upper-cased, a dot becomes `_` and a hyphen `__`: `ghe.my-corp.com` gives
     *   `GHE_MY__CORP_COM`. A label never starts or ends with a hyphen, so a run of underscores is
     *   either a single dot or the hyphens of one label;
     * - an IPv6 address starts with `_` and lists all eight groups in hex: `[::1]` gives
     *   `_0_0_0_0_0_0_0_1`;
     * - a non-default port follows after `___`: `ghe.example.com:8443` gives `GHE_EXAMPLE_COM___8443`.
     *
     * The scheme is not a part: plain HTTP never carries a token to a remote host anyway.
     *
     * @return non-empty-string
     */
    public function tokenVariable(): string
    {
        if (\str_starts_with($this->host, '[')) {
            /** @var array<int, int> $groups `fromString()` has validated the address */
            $groups = \unpack('n8', (string) \inet_pton(\substr($this->host, 1, -1)));
            $host = '_' . \implode('_', \array_map(\dechex(...), $groups));
        } else {
            $host = \strtr($this->host, ['.' => '_', '-' => '__']);
        }

        $port = $this->port === null ? '' : '___' . $this->port;

        return \strtoupper('DLOAD_TOKEN_' . $host . $port);
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
