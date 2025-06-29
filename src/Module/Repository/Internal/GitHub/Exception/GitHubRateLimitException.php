<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\Repository\Internal\GitHub\Exception;

/**
 * Exception thrown when GitHub API rate limit is exceeded.
 *
 * @internal
 * @psalm-internal Internal\DLoad\Module\Repository\Internal\GitHub
 */
final class GitHubRateLimitException extends \RuntimeException
{
    public function __construct(
        public readonly string $documentationUrl,
        string $message = 'GitHub API rate limit exceeded',
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    /**
     * Creates exception from GitHub API response body.
     *
     * @param array{0: string, 1: string} $responseData
     */
    public static function fromApiResponse(array $responseData): self
    {
        return new self(
            documentationUrl: $responseData[1],
            message: $responseData[0],
        );
    }
}
