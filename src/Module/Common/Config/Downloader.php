<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\Common\Config;

use Internal\DLoad\Module\Common\Internal\Attribute\XPath;

/**
 * Downloader configuration.
 *
 * Contains global settings for the download functionality.
 */
final class Downloader
{
    /** @var string|null $tmpDir Temporary directory for downloads */
    #[XPath('/dload/@temp-dir')]
    public ?string $tmpDir = null;
}
