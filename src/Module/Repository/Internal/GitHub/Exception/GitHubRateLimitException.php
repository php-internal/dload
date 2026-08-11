<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\Repository\Internal\GitHub\Exception;

use Internal\DLoad\Module\Repository\Exception\RateLimitException;

/**
 * Exception thrown when GitHub API rate limit is exceeded.
 *
 * @internal
 * @psalm-internal Internal\DLoad\Module\Repository
 */
final class GitHubRateLimitException extends RateLimitException
{
    /**
     * @param non-empty-string $message
     * @param non-empty-string|null $repository Repository identifier, e.g. `owner/repo`.
     */
    public function __construct(
        string $message = 'GitHub API rate limit exceeded. Check the GitHub Token or try again later.',
        ?string $repository = null,
        ?string $documentationUrl = null,
        ?\DateTimeImmutable $resetAt = null,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $repository, $documentationUrl, $resetAt, $previous);
    }
}
