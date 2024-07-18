<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\Common\Config\Embed;

use Internal\DLoad\Module\Common\Internal\Attribute\XPath;
use Internal\DLoad\Module\Common\Internal\Attribute\XPathEmbedList;

final class Software
{
    #[XPath('@name')]
    public string $name;

    /**
     * If {@see null}, the name in lower case will be used.
     */
    #[XPath('@alias')]
    public ?string $alias = null;

    #[XPath('@description')]
    public string $description = '';

    #[XPathEmbedList('repository', Repository::class)]
    public array $repositories = [];

    #[XPathEmbedList('file', File::class)]
    public array $files = [];
}
