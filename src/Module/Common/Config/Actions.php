<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\Common\Config;

use Internal\DLoad\Module\Common\Config\Action\Download;
use Internal\DLoad\Module\Common\Internal\Attribute\XPathEmbedList;

/**
 * @internal
 */
final class Actions
{
    #[XPathEmbedList('/dload/actions/download', Download::class)]
    public array $downloads = [];
}
