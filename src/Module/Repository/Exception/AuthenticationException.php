<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\Repository\Exception;

/**
 * Repository API rejected the provided credentials (HTTP 401).
 *
 * @internal
 */
final class AuthenticationException extends RepositoryException {}
