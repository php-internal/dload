<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\Repository\Exception;

/**
 * Requested repository does not exist or is not visible with the current credentials (HTTP 404).
 *
 * @internal
 */
final class RepositoryNotFoundException extends RepositoryException {}
