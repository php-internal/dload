<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\Common\Config;

use Internal\DLoad\Module\Common\Internal\Attribute\XPath;
use Internal\DLoad\Module\Common\Internal\Attribute\XPathEmbedList;

final class CustomSoftwareRegistry
{
    #[XPath('/dload/registry/@overwrite')]
    public bool $overwrite = false;

    /**
     * @var Embed\Software[]
     */
    #[XPathEmbedList('/dload/registry/software', Embed\Software::class)]
    public array $software = [];
}
