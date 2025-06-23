<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\Config\Schema\Embed;

use Internal\DLoad\Module\Common\Internal\Attribute\XPath;

/**
 * Binary configuration.
 *
 * Defines a binary executable that can be checked for existence
 * to avoid re-downloading.
 *
 * ```php
 * $binary = Binary::fromArray([
 *     'name' => 'rr',
 *     'pattern' => '/^roadrunner(?:\.exe)?$/',
 *     'version-command' => '--version'
 * ]);
 * ```
 *
 * @psalm-type BinaryArray = array{
 *     name: non-empty-string,
 *     pattern?: non-empty-string,
 *     version-command?: non-empty-string
 * }
 */
final class Binary
{
    /** @var non-empty-string $name Binary executable name */
    #[XPath('@name')]
    public string $name;

    /** @var non-empty-string|null $pattern Regular expression pattern to match binary file during extraction */
    #[XPath('@pattern')]
    public ?string $pattern = null;

    /** @var non-empty-string|null $versionCommand Command argument to check binary version (e.g. "--version") */
    #[XPath('@version-command')]
    public ?string $versionCommand = null;

    /**
     * Creates a Binary configuration from an array.
     *
     * @param BinaryArray $binaryArray Configuration array
     */
    public static function fromArray(array $binaryArray): self
    {
        $self = new self();
        $self->name = $binaryArray['name'];
        $self->pattern = $binaryArray['pattern'] ?? null;
        $self->versionCommand = $binaryArray['version-command'] ?? null;

        return $self;
    }
}
