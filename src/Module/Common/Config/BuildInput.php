<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\Common\Config;

use Internal\DLoad\Module\Common\Internal\Attribute\InputOption;

/**
 * @internal
 * @psalm-internal Internal\DLoad\Module\Common
 */
final class BuildInput
{
    #[InputOption('arch')]
    public ?string $arch = null;

    #[InputOption('stability')]
    public ?string $stability = null;

    #[InputOption('os')]
    public ?string $os = null;
}
