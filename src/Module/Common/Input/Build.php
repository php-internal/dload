<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\Common\Input;

use Internal\DLoad\Module\Common\Architecture;
use Internal\DLoad\Module\Common\Internal\Attribute\InputOption;
use Internal\DLoad\Module\Common\OperatingSystem;
use Internal\DLoad\Module\Common\Stability;

/**
 * Build configuration options.
 *
 * Contains input options that define the build parameters for software selection.
 * These values are typically provided via command-line options.
 *
 * @internal
 */
final class Build
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
