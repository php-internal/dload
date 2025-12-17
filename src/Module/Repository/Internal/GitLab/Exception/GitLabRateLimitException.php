<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\Repository\Internal\GitLab\Exception;

/**
 * Exception thrown when GitLab API rate limit is exceeded.
 *
 * @internal
 * @psalm-internal Internal\DLoad\Module\Repository\Internal\GitLab
 */
final class GitLabRateLimitException extends \RuntimeException
{
    public function __construct(
        string $message = 'GitLab API rate limit exceeded. Check the GitLab Token or try again later.',
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }
}
