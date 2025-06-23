<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\Config\Schema\Embed;

use Internal\DLoad\Module\Common\Internal\Attribute\XPath;
use Internal\DLoad\Module\Common\Internal\Attribute\XPathEmbed;
use Internal\DLoad\Module\Common\Internal\Attribute\XPathEmbedList;

/**
 * Software configuration entity.
 *
 * Represents a software package that can be downloaded through the system.
 * Contains all necessary metadata for proper identification and retrieval.
 *
 * ```php
 * $software = Software::fromArray([
 *     'name' => 'RoadRunner',
 *     'alias' => 'rr',
 *     'description' => 'High performance PHP application server',
 *     'repositories' => [
 *         ['type' => 'github', 'uri' => 'roadrunner-server/roadrunner']
 *     ],
 * ]);
 * ```
 *
 * @psalm-import-type RepositoryArray from Repository
 * @psalm-import-type FileArray from File
 * @psalm-import-type BinaryArray from Binary
 * @psalm-type SoftwareArray = array{
 *     name: non-empty-string,
 *     alias?: non-empty-string,
 *     homepage?: non-empty-string,
 *     description?: non-empty-string,
 *     repositories?: list<RepositoryArray>,
 *     files?: list<FileArray>,
 *     binary?: BinaryArray,
 * }
 */
final class Software
{
    /** @var non-empty-string $name Software package name */
    #[XPath('@name')]
    public string $name;

    /**
     * @var non-empty-string|null $alias CLI command alias
     *      If null, the name in lowercase will be used as the identifier.
     */
    #[XPath('@alias')]
    public ?string $alias = null;

    /** @var string|null $homepage Official software homepage URL */
    #[XPath('@homepage')]
    public ?string $homepage = null;

    /** @var string $description Short description of the software */
    #[XPath('@description')]
    public string $description = '';

    /** @var Binary|null $binary Primary binary for this software */
    #[XPathEmbed('binary', Binary::class)]
    public ?Binary $binary = null;

    /** @var Repository[] $repositories List of repositories where the software can be found */
    #[XPathEmbedList('repository', Repository::class)]
    public array $repositories = [];

    /** @var list<File> $files List of files to be extracted after download */
    #[XPathEmbedList('file', File::class)]
    public array $files = [];

    /**
     * Creates a Software instance from array configuration.
     *
     * @param SoftwareArray $softwareArray Configuration array
     */
    public static function fromArray(mixed $softwareArray): self
    {
        $self = new self();
        $self->name = $softwareArray['name'];
        $self->alias = $softwareArray['alias'] ?? null;
        $self->homepage = $softwareArray['homepage'] ?? null;
        $self->description = $softwareArray['description'] ?? '';
        $self->binary = isset($softwareArray['binary']) ? Binary::fromArray($softwareArray['binary']) : null;
        $self->repositories = \array_map(
            static fn(array $repositoryArray): Repository => Repository::fromArray($repositoryArray),
            $softwareArray['repositories'] ?? [],
        );

        $self->files = \array_map(
            static fn(array $fileArray): File => File::fromArray($fileArray),
            $softwareArray['files'] ?? [],
        );

        return $self;
    }

    /**
     * Returns the software identifier.
     *
     * @return non-empty-string Software identifier (alias or lowercase name)
     */
    public function getId(): string
    {
        return $this->alias ?? \strtolower($this->name);
    }
}
