<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\Common\Config;

use Internal\DLoad\Module\Common\Internal\Attribute\XPath;

final class Downloader
{
    #[XPath('/dload/@temp-dir')]
    public ?string $tmpDir = null;
}
