<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\Repository\Internal\GitLab\Exception;

use Internal\DLoad\Module\Repository\Exception\RateLimitException;

/**
 * Exception thrown when GitLab API rate limit is exceeded.
 *
 * @internal
 * @psalm-internal Internal\DLoad\Module\Repository
 */
final class GitLabRateLimitException extends RateLimitException
{
    /**
     * @param non-empty-string $message
     * @param non-empty-string|null $repository Repository identifier, e.g. `group/project`.
     */
    public function __construct(
        string $message = 'GitLab API rate limit exceeded. Check the GitLab Token or try again later.',
        ?string $repository = null,
        ?\DateTimeImmutable $resetAt = null,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $repository, null, $resetAt, $previous);
    }
}
