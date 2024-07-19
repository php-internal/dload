<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\Common\Config\Action;

use Internal\DLoad\Module\Common\Internal\Attribute\XPath;

/**
 * @internal
 */
final class Download
{
    #[XPath('@software')]
    public string $software;

    #[XPath('@version')]
    public ?string $version = null;
}
