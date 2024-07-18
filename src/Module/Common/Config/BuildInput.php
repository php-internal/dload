<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\Common\Config;

use Internal\DLoad\Module\Common\Architecture;
use Internal\DLoad\Module\Common\Internal\Attribute\InputOption;
use Internal\DLoad\Module\Common\OperatingSystem;
use Internal\DLoad\Module\Common\Stability;

/**
 * @internal
 */
final class BuildInput
{
    /**
     * Use {@see Architecture} to get final value.
     */
    #[InputOption('arch')]
    public ?string $arch = null;

    /**
     * Use {@see Stability} to get final value.
     */
    #[InputOption('stability')]
    public ?string $stability = null;

    /**
     * Use {@see OperatingSystem} to get final value.
     */
    #[InputOption('os')]
    public ?string $os = null;

    #[InputOption('version')]
    public ?string $version = null;
}
