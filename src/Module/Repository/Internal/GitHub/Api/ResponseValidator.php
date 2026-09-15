<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\Repository\Internal\GitHub\Api;

use Internal\DLoad\Module\Repository\Exception\RateLimitException;
use Internal\DLoad\Module\Repository\Internal\GitHub\Exception\GitHubRateLimitException;
use Internal\DLoad\Module\Repository\Internal\ResponseValidator as BaseValidator;

/**
 * GitHub flavored response validator.
 *
 * @internal
 * @psalm-internal Internal\DLoad\Module\Repository\Internal\GitHub
 */
final class ResponseValidator extends BaseValidator
{
    protected function providerName(): string
    {
        return 'GitHub';
    }

    protected function tokenEnvVariable(): string
    {
        return 'GITHUB_TOKEN';
    }

    protected function isAssetUri(string $uri): bool
    {
        // Assets are served from github.com (and its CDN), the API lives on api.github.com
        return \str_contains($uri, '/releases/download/');
    }

    protected function repositoryFromUri(string $uri): ?string
    {
        // API calls: https://api.github.com/repos/owner/repo/releases
        // Asset downloads: https://github.com/owner/repo/releases/download/v1.0.0/asset.zip
        foreach (['~/repos/([^/?#]+/[^/?#]+)~', '~github\.com/([^/?#]+/[^/?#]+)~'] as $pattern) {
            if (\preg_match($pattern, $uri, $matches) === 1 && $matches[1] !== '') {
                /** @var non-empty-string */
                return $matches[1];
            }
        }

        return null;
    }

    protected function anonymousRateLimit(): ?int
    {
        return 60;
    }

    protected function authenticatedRateLimit(): ?int
    {
        return 5000;
    }

    protected function instantiateRateLimitException(
        string $message,
        ?string $repository,
        ?\DateTimeImmutable $resetAt,
    ): RateLimitException {
        return new GitHubRateLimitException(
            message: $message,
            repository: $repository,
            resetAt: $resetAt,
        );
    }
}
