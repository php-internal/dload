<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\Common\Config\Embed;

use Internal\DLoad\Module\Common\Internal\Attribute\XPath;
use Internal\DLoad\Module\Common\Internal\Attribute\XPathEmbedList;

final class Software
{
    /**
     * @var non-empty-string
     */
    #[XPath('@name')]
    public string $name;

    /**
     * If {@see null}, the name in lower case will be used.
     * @var non-empty-string|null
     */
    #[XPath('@alias')]
    public ?string $alias = null;

    #[XPath('@description')]
    public string $description = '';

    /** @var list<Repository> */
    #[XPathEmbedList('repository', Repository::class)]
    public array $repositories = [];

    /** @var list<File> */
    #[XPathEmbedList('file', File::class)]
    public array $files = [];

    /**
     * @return non-empty-string
     */
    public function getId(): string
    {
        return $this->alias ?? \strtolower($this->name);
    }
}
