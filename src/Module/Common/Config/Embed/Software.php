<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\Common\Config\Embed;

use Internal\DLoad\Module\Common\Internal\Attribute\XPath;
use Internal\DLoad\Module\Common\Internal\Attribute\XPathEmbedList;

/**
 * @psalm-import-type RepositoryArray from Repository
 * @psalm-import-type FileArray from File
 * @psalm-type SoftwareArray = array{
 *     name: non-empty-string,
 *     alias?: non-empty-string,
 *     homepage?: non-empty-string,
 *     description?: non-empty-string,
 *     repositories?: list<RepositoryArray>,
 *     files?: list<FileArray>
 * }
 */
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

    #[XPath('@homepage')]
    public ?string $homepage = null;

    #[XPath('@description')]
    public string $description = '';

    /** @var File */
    #[XPathEmbedList('repository', Repository::class)]
    public array $repositories = [];

    /** @var File */
    #[XPathEmbedList('file', File::class)]
    public array $files = [];

    /**
     * @param SoftwareArray $softwareArray
     */
    public static function fromArray(mixed $softwareArray): self
    {
        $self = new self();
        $self->name = $softwareArray['name'];
        $self->alias = $softwareArray['alias'] ?? null;
        $self->homepage = $softwareArray['homepage'] ?? null;
        $self->description = $softwareArray['description'] ?? '';
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
     * @return non-empty-string
     */
    public function getId(): string
    {
        return $this->alias ?? \strtolower($this->name);
    }
}
