<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\Repository\Exception;

/**
 * A `<repository>` config that addresses no repository: an unknown type or an unparseable URI.
 *
 * Nothing was requested from the repository side, so the message names the config value to fix.
 *
 * @internal
 */
final class InvalidRepositoryConfigException extends \InvalidArgumentException {}
