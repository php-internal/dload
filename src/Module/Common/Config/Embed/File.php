<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\Common\Config\Embed;

use Internal\DLoad\Module\Common\Internal\Attribute\XPath;

final class File
{
    /**
     * @var non-empty-string|null In case of not null, found file will be renamed to this value with the same extension.
     */
    #[XPath('@rename')]
    public ?string $rename = null;

    #[XPath('@pattern')]
    public string $pattern = '/^.*$/';
}
