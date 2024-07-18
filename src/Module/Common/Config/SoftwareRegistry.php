<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\Common\Config;

use Internal\DLoad\Module\Common\Internal\Attribute\XPathEmbedList;

final class SoftwareRegistry
{
    #[XPathEmbedList('/dload/registry/software', Embed\Software::class)]
    public array $software = [];
}
