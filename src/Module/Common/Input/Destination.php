<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\Common\Input;

use Internal\DLoad\Module\Common\Internal\Attribute\InputOption;

/**
 * @internal
 */
final class Destination
{
    #[InputOption('path')]
    public ?string $path = null;
}
