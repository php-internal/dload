<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\Common\Config\Embed;

use Internal\DLoad\Module\Common\Internal\Attribute\XPath;

/**
 * @psalm-type FileArray = array{
 *     rename?: non-empty-string,
 *     pattern?: non-empty-string
 * }
 */
final class File
{
    /**
     * @var non-empty-string|null In case of not null, found file will be renamed to this value with the same extension.
     */
    #[XPath('@rename')]
    public ?string $rename = null;

    #[XPath('@pattern')]
    public string $pattern = '/^.*$/';

    /**
     * @param FileArray $fileArray
     */
    public static function fromArray(mixed $fileArray): self
    {
        $self = new self();
        $self->rename = $fileArray['rename'] ?? null;
        $self->pattern = $fileArray['pattern'] ?? '/^.*$/';

        return $self;
    }
}
