<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\Repository\Exception;

/**
 * A release asset is no longer available at the address the repository listed it under.
 *
 * Usually the release was deleted or its assets were replaced after the listing was fetched.
 * The listing itself may still be fine, so this is not a repository-level failure.
 */
final class AssetNotFoundException extends RepositoryException {}
