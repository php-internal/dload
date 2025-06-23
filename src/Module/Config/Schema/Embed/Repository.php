<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\Config\Schema\Embed;

use Internal\DLoad\Module\Common\Internal\Attribute\XPath;

/**
 * Repository configuration.
 *
 * Defines a source repository for software packages.
 *
 * ```php
 * $repo = Repository::fromArray([
 *     'type' => 'github',
 *     'uri' => 'roadrunner-server/roadrunner',
 *     'asset-pattern' => '#^roadrunner-.*#'
 * ]);
 * ```
 *
 * @psalm-type RepositoryArray = array{
 *     type: non-empty-string,
 *     uri: non-empty-string,
 *     asset-pattern?: non-empty-string
 * }
 */
final class Repository
{
    /** @var non-empty-string $type Repository type identifier */
    #[XPath('@type')]
    public string $type = 'github';

    /** @var non-empty-string $uri Repository URI identifier */
    #[XPath('@uri')]
    public string $uri;

    /** @var non-empty-string $assetPattern Regular expression pattern to match assets */
    #[XPath('@asset-pattern')]
    public string $assetPattern = '/^.*$/';

    /**
     * Creates a Repository configuration from an array.
     *
     * @param RepositoryArray $repositoryArray Configuration array
     */
    public static function fromArray(mixed $repositoryArray): self
    {
        $self = new self();
        $self->type = $repositoryArray['type'] ?? 'github';
        $self->uri = $repositoryArray['uri'];
        $self->assetPattern = $repositoryArray['asset-pattern'] ?? '/^.*$/';

        return $self;
    }
}
