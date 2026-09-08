<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\Config\Schema;

use Internal\DLoad\Module\Common\Internal\Attribute\InflectableConfig;
use Internal\DLoad\Module\Common\Internal\Attribute\XPath;

/**
 * Downloader configuration.
 *
 * Contains global settings for the download functionality.
 */
#[InflectableConfig]
final class Downloader
{
    /** @var non-empty-string|null $tmpDir Temporary directory for downloads */
    #[XPath('/dload/@temp-dir')]
    public ?string $tmpDir = null;
}
