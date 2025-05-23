<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\Common\Config;

use Internal\DLoad\Module\Common\Config\Action\Download;
use Internal\DLoad\Module\Common\Internal\Attribute\XPathEmbedList;

/**
 * Configuration actions container.
 *
 * Contains the list of download actions defined in the configuration file.
 *
 * @internal
 */
final class Actions
{
    /** @var list<Download> $downloads Collection of download actions */
    #[XPathEmbedList('/dload/actions/download', Download::class)]
    public array $downloads = [];
}
