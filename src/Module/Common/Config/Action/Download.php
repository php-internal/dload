<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\Common\Config\Action;

use Internal\DLoad\Module\Common\Internal\Attribute\XPath;

/**
 * Download action configuration.
 *
 * Represents a single download action from the configuration file.
 * Defines what software to download considering its version constraint.
 *
 * ```php
 * $action = Download::fromSoftwareId('roadrunner');
 * ```
 *
 * @internal
 */
final class Download
{
    /** @var non-empty-string $software Software identifier to download */
    #[XPath('@software')]
    public string $software;

    /** @var non-empty-string|null $version Version constraint (composer-style) */
    #[XPath('@version')]
    public ?string $version = null;

    /**
     * Creates a download action from a software identifier.
     *
     * @param non-empty-string $software Software identifier
     */
    public static function fromSoftwareId(string $software): self
    {
        $action = new self();
        $action->software = $software;
        return $action;
    }
}
