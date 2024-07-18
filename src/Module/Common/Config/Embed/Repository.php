<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\Common\Config\Embed;

use Internal\DLoad\Module\Common\Internal\Attribute\XPath;

final class Repository
{
    #[XPath('@type')]
    public string $type = 'github';
    #[XPath('@uri')]
    public string $uri;
    #[XPath('@pattern')]
    public string $pattern = '/^.*$/';
}
