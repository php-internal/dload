<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\Repository\Internal;

use Internal\DLoad\Module\Repository\Exception\InvalidRepositoryConfigException;

/**
 * Where a repository lives, read from the `uri` of its configuration: the server and the path there.
 *
 * - A path, `owner/repo`, names a repository on the public host.
 * - A URL with a scheme names the server too: its scheme, host and port are the server, its path is
 *   the repository. A URL of the public host, `https://github.com/owner/repo`, keeps the public host.
 * - A path that starts with the public host, `github.com/owner/repo`, is read as its URL.
 *
 * A server is never derived from a value without a scheme: `ghe.example.com/owner/repo` is a path on
 * the public host, so a token of the public host never goes to a host the config only hints at.
 *
 * A trailing slash and a `.git` suffix are dropped. Neither GitHub nor GitLab allows a repository
 * name ending with `.git`, so a clone URL works too.
 *
 * ```php
 * $location = RepositoryLocation::fromUri('https://gitlab.example.com:8443/group/sub/project', 'https://gitlab.com');
 * echo $location->server; // https://gitlab.example.com:8443
 * echo $location->path(); // group/sub/project
 * ```
 *
 * @internal
 */
final class RepositoryLocation
{
    /**
     * @param non-empty-string $uri The configured value, for error messages.
     * @param Server|null $server Null for the public host.
     * @param non-empty-list<non-empty-string> $segments
     */
    private function __construct(
        public readonly string $uri,
        public readonly ?Server $server,
        public readonly array $segments,
    ) {}

    /**
     * @param non-empty-string $publicServer URL of the public host of the repository type.
     * @throws InvalidRepositoryConfigException When the value is neither a repository path nor a repository URL.
     */
    public static function fromUri(string $uri, string $publicServer): self
    {
        $value = \trim($uri);
        $value === '' and throw new InvalidRepositoryConfigException('Repository URI is empty.');
        $fail = static fn(string $reason): InvalidRepositoryConfigException => self::error($value, $reason);
        $public = Server::fromString($publicServer);

        $server = null;
        $path = $value;
        if (\preg_match('#^([a-z][a-z0-9+.-]*://[^/?\#]*)(.*)$#is', $value, $match) === 1) {
            try {
                $server = Server::fromString($match[1]);
            } catch (\InvalidArgumentException $e) {
                throw self::error($value, \lcfirst($e->getMessage()), $e);
            }

            $path = $match[2];
        }

        \strpbrk($path, '?#') === false or throw $fail('a query or fragment is not allowed.');

        $path = \trim($path, '/');
        $path === '' and throw $fail('no repository path.');
        $segments = \explode('/', $path);
        if ($server === null && \strtolower($segments[0]) === $public->host && \count($segments) > 1) {
            \array_shift($segments);
        }

        // The public host is always reached over HTTPS on its default port, whatever the URL says
        if ($server !== null && $server->host === $public->host && $server->port === null) {
            $server = null;
        }

        $last = \array_key_last($segments);
        \str_ends_with($segments[$last], '.git') and $segments[$last] = \substr($segments[$last], 0, -4);

        foreach ($segments as $segment) {
            $segment === '-' and throw $fail('the URL points inside a repository; give the repository URL itself.');
            \in_array($segment, ['', '.', '..'], true) and throw $fail('the repository path is malformed.');
        }

        /** @var non-empty-list<non-empty-string> $segments */
        return new self($value, $server, $segments);
    }

    /**
     * @return non-empty-string Segments joined with `/`, no surrounding slashes.
     */
    public function path(): string
    {
        return \implode('/', $this->segments);
    }

    /**
     * An error about this repository, for checks that depend on the repository type.
     */
    public function invalid(string $reason): InvalidRepositoryConfigException
    {
        return self::error($this->uri, $reason);
    }

    private static function error(string $uri, string $reason, ?\Throwable $previous = null): InvalidRepositoryConfigException
    {
        return new InvalidRepositoryConfigException(\sprintf('Repository `%s`: %s', $uri, $reason), previous: $previous);
    }
}
