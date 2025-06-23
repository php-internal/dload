<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\Config\Schema;

use Internal\DLoad\Module\Common\Internal\Attribute\XPath;
use Internal\DLoad\Module\Common\Internal\Attribute\XPathEmbedList;

/**
 * Custom software registry configuration.
 *
 * Holds settings for custom software definitions provided in the configuration.
 */
final class CustomSoftwareRegistry
{
    /** @var bool $overwrite Replace the built-in software collection with custom ones */
    #[XPath('/dload/registry/@overwrite')]
    public bool $overwrite = false;

    /**
     * @var \Internal\DLoad\Module\Config\Schema\Embed\Software[] $software Custom software definitions from the configuration
     */
    #[XPathEmbedList('/dload/registry/software', \Internal\DLoad\Module\Config\Schema\Embed\Software::class)]
    public array $software = [];
}
