<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\Repository\Exception;

/**
 * Repository API denied the request (HTTP 403) for a reason other than rate limiting.
 *
 * @internal
 */
final class AccessDeniedException extends RepositoryException {}
