<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\Registry;

/**
 * Identity of a software repository in the version registry.
 *
 * The same repository may be referenced by several software packages and by several configs,
 * so the registry keys its records by the repository type, server and path rather than by
 * software name. The path is the one the repository reports, not the configured URI: a factory
 * may accept a full URL and reduce it. GitHub and GitLab resolve paths case-insensitively, so the
 * identity is normalized: lower case, no surrounding slashes.
 *
 * ```php
 * $id = new RepositoryId('github', 'roadrunner-server/roadrunner');
 * echo $id; // github:roadrunner-server/roadrunner
 *
 * $id = new RepositoryId('github', 'roadrunner-server/roadrunner', 'ghe.example.com');
 * echo $id; // github@ghe.example.com:roadrunner-server/roadrunner
 * ```
 *
 * @internal
 */
final class RepositoryId implements \Stringable
{
    /** @var non-empty-string Repository type, e.g. `github` or `gitlab`. */
    public readonly string $type;

    /** @var non-empty-string Repository path within the type, e.g. `owner/repo`. */
    public readonly string $uri;

    /** @var non-empty-string|null Host and port of a self-hosted instance, null for the public host of the type. */
    public readonly ?string $server;

    /**
     * @param non-empty-string $type
     * @param non-empty-string $uri
     * @param non-empty-string|null $server
     * @throws \InvalidArgumentException When the URI has nothing but slashes and spaces.
     */
    public function __construct(string $type, string $uri, ?string $server = null)
    {
        $normalized = \strtolower(\trim($uri, " \t\n\r/"));
        $normalized === '' and throw new \InvalidArgumentException(\sprintf('Repository URI `%s` is empty.', $uri));

        $this->type = \strtolower($type);
        $this->uri = $normalized;
        $this->server = $server === null ? null : \strtolower($server);
    }

    public function equals(self $other): bool
    {
        return $this->type === $other->type && $this->uri === $other->uri && $this->server === $other->server;
    }

    /**
     * @return non-empty-string
     */
    public function __toString(): string
    {
        return $this->type . ($this->server === null ? '' : '@' . $this->server) . ':' . $this->uri;
    }
}
