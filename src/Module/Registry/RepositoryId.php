<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\Registry;

/**
 * Identity of a software repository in the version registry.
 *
 * The same repository may be referenced by several software packages and by several configs,
 * so the registry keys its records by the repository type and path rather than by software name.
 * The path is the one the repository reports, not the configured URI: a factory may accept a full
 * URL and reduce it. GitHub and GitLab resolve paths case-insensitively, so the identity is
 * normalized: lower case, no surrounding slashes.
 *
 * ```php
 * $id = new RepositoryId('github', $repository->getName());
 * echo $id; // github:roadrunner-server/roadrunner
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

    /**
     * @param non-empty-string $type
     * @param non-empty-string $uri
     * @throws \InvalidArgumentException When the URI has nothing but slashes and spaces.
     */
    public function __construct(string $type, string $uri)
    {
        $normalized = \strtolower(\trim($uri, " \t\n\r/"));
        $normalized === '' and throw new \InvalidArgumentException(\sprintf('Repository URI `%s` is empty.', $uri));

        $this->type = \strtolower($type);
        $this->uri = $normalized;
    }

    public function equals(self $other): bool
    {
        return $this->type === $other->type && $this->uri === $other->uri;
    }

    /**
     * @return non-empty-string
     */
    public function __toString(): string
    {
        return $this->type . ':' . $this->uri;
    }
}
