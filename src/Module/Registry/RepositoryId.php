<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\Registry;

use Internal\DLoad\Module\Config\Schema\Embed\Repository as RepositoryConfig;

/**
 * Identity of a software repository in the version registry.
 *
 * The same repository may be referenced by several software packages and by several configs,
 * so the registry keys its records by the repository type and URI rather than by software name.
 *
 * ```php
 * $id = RepositoryId::fromConfig($repositoryConfig);
 * echo $id; // github:roadrunner-server/roadrunner
 * ```
 */
final class RepositoryId implements \Stringable
{
    /**
     * @param non-empty-string $type Repository type, e.g. `github` or `gitlab`.
     * @param non-empty-string $uri Repository URI within the type, e.g. `owner/repo`.
     */
    public function __construct(
        public readonly string $type,
        public readonly string $uri,
    ) {}

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
