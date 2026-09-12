<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\Repository\Internal\GitLab\Api;

use Internal\DLoad\Module\Repository\Exception\RateLimitException;
use Internal\DLoad\Module\Repository\Internal\GitLab\Exception\GitLabRateLimitException;
use Internal\DLoad\Module\Repository\Internal\ResponseValidator as BaseValidator;

/**
 * GitLab flavored response validator.
 *
 * @internal
 * @psalm-internal Internal\DLoad\Module\Repository\Internal\GitLab
 */
final class ResponseValidator extends BaseValidator
{
    protected function providerName(): string
    {
        return 'GitLab';
    }

    protected function tokenEnvVariable(): string
    {
        return 'GITLAB_TOKEN';
    }

    protected function repositoryTerm(): string
    {
        return 'project';
    }

    protected function isAssetUri(string $uri): bool
    {
        // https://gitlab.com/api/v4/projects/group%2Fproject/releases/v1.0.0/downloads/asset.zip
        return \preg_match('~/releases/[^/?#]+/downloads/~', $uri) === 1;
    }

    protected function repositoryFromUri(string $uri): ?string
    {
        // https://gitlab.com/api/v4/projects/group%2Fproject/releases
        $matched = \preg_match('~/projects/([^/?#]+)~', $uri, $matches) === 1;
        if (!$matched) {
            return null;
        }

        $path = \urldecode($matches[1]);

        /** @var non-empty-string|null */
        return $path === '' ? null : $path;
    }

    protected function anonymousRateLimit(): ?int
    {
        return null;
    }

    protected function authenticatedRateLimit(): ?int
    {
        return null;
    }

    protected function instantiateRateLimitException(
        string $message,
        ?string $repository,
        ?\DateTimeImmutable $resetAt,
    ): RateLimitException {
        return new GitLabRateLimitException(
            message: $message,
            repository: $repository,
            resetAt: $resetAt,
        );
    }
}
