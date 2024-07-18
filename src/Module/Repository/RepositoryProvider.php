<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\Repository;

use Internal\DLoad\Module\Common\Config\Embed\Repository;
use Internal\DLoad\Module\Repository\Internal\GitHub\Factory as GithubFactory;

/**
 * @internal
 */
final class RepositoryProvider
{
    public function __construct(
        private readonly GithubFactory $githubFactory,
    ) {}

    public function getByConfig(Repository $config): RepositoryInterface
    {
        return match (\strtolower($config->type)) {
            'github' => $this->githubFactory->create($config->uri),
            default => throw new \RuntimeException("Unknown repository type `$config->type`."),
        };
    }
}
