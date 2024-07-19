<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\Common\Config\Action;

use Internal\DLoad\Module\Common\Internal\Attribute\XPath;

/**
 * @internal
 */
final class Download
{
    /** @var non-empty-string */
    #[XPath('@software')]
    public string $software;

    /** @var non-empty-string|null */
    #[XPath('@version')]
    public ?string $version = null;

    /**
     * @param non-empty-string $software
     */
    public static function fromSoftwareId(string $software): self
    {
        $action = new self();
        $action->software = $software;
        return $action;
    }
}
