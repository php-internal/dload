<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\Common\Config\Embed;

use Internal\DLoad\Module\Common\Internal\Attribute\XPath;

/**
 * File configuration.
 *
 * Defines how a file should be extracted and optionally renamed after download.
 *
 * ```php
 * $file = File::fromArray([
 *     'pattern' => '/^roadrunner(?:\.exe)?$/',
 *     'rename' => 'rr'
 * ]);
 * ```
 *
 * @psalm-type FileArray = array{
 *     rename?: non-empty-string,
 *     pattern?: non-empty-string
 * }
 */
final class File
{
    /**
     * @var non-empty-string|null $rename In case of not null, found file will be renamed to this value
     *      with the same extension.
     */
    #[XPath('@rename')]
    public ?string $rename = null;

    /** @var non-empty-string $pattern Regular expression pattern to match files */
    #[XPath('@pattern')]
    public string $pattern = '/^.*$/';

    /**
     * Creates a File configuration from an array.
     *
     * @param FileArray $fileArray Configuration array
     */
    public static function fromArray(mixed $fileArray): self
    {
        $self = new self();
        $self->rename = $fileArray['rename'] ?? null;
        $self->pattern = $fileArray['pattern'] ?? '/^.*$/';

        return $self;
    }
}
