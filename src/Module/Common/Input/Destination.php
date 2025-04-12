<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\Common\Input;

use Internal\DLoad\Module\Common\Internal\Attribute\InputOption;

/**
 * Destination configuration.
 *
 * Contains the destination path where downloaded software will be saved.
 *
 * @internal
 */
final class Destination
{
    /** @var non-empty-string|null $path Target path for downloaded files */
    #[InputOption('path')]
    public ?string $path = null;
}
