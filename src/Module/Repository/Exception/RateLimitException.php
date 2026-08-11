<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\Repository\Exception;

/**
 * Repository API rate limit is exceeded.
 *
 * @internal
 */
class RateLimitException extends RepositoryException
{
    /**
     * @param non-empty-string $message
     * @param non-empty-string|null $repository Repository identifier, e.g. `owner/repo`.
     * @param \DateTimeImmutable|null $resetAt Moment when the limit is reset, if the API reported it.
     */
    public function __construct(
        string $message,
        ?string $repository = null,
        public readonly ?string $documentationUrl = null,
        public readonly ?\DateTimeImmutable $resetAt = null,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $repository, $previous);
    }
}
