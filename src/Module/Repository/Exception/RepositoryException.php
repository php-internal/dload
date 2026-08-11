<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\Repository\Exception;

/**
 * Base exception for failures happened on the repository (API) side.
 *
 * Messages of these exceptions are shown to the user as is, so they must explain
 * what happened and how the problem may be fixed.
 *
 * @internal
 */
abstract class RepositoryException extends \RuntimeException
{
    /**
     * @param non-empty-string $message Human-readable explanation with a hint on how to fix the problem.
     * @param non-empty-string|null $repository Repository identifier, e.g. `owner/repo`.
     */
    public function __construct(
        string $message,
        public readonly ?string $repository = null,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }
}
