<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\Config\Schema;

use Internal\DLoad\Module\Common\Internal\Attribute\XPathEmbedList;
use Internal\DLoad\Module\Config\Schema\Action\Download;
use Internal\DLoad\Module\Config\Schema\Action\Velox;

/**
 * Configuration actions container.
 *
 * Contains the list of actions defined in the configuration file,
 * including both download and build actions.
 *
 * @internal
 */
final class Actions
{
    /** @var list<Download> $downloads Collection of download actions */
    #[XPathEmbedList('/dload/actions/download', Download::class)]
    public array $downloads = [];

    /** @var list<Velox> $veloxBuilds Collection of velox build actions */
    #[XPathEmbedList('/dload/actions/velox', Velox::class)]
    public array $veloxBuilds = [];
}
