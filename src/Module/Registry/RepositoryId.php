<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\Registry;

use Internal\DLoad\Module\Config\Schema\Embed\Repository as RepositoryConfig;

/**
 * Identity of a software repository in the version registry.
 *
 * The same repository may be referenced by several software packages and by several configs,
 * so the registry keys its records by the repository type and URI rather than by software name.
 * GitHub and GitLab resolve paths case-insensitively, so the identity is normalized: lower case,
 * no surrounding slashes.
 *
 * ```php
 * $id = RepositoryId::fromConfig($repositoryConfig);
 * echo $id; // github:roadrunner-server/roadrunner
 * ```
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

    public static function fromConfig(RepositoryConfig $config): self
    {
        return new self($config->type, $config->uri);
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
