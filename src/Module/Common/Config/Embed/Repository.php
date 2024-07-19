<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\Common\Config\Embed;

use Internal\DLoad\Module\Common\Internal\Attribute\XPath;

/**
 * @psalm-type RepositoryArray = array{
 *     type: non-empty-string,
 *     uri: non-empty-string,
 *     asset-pattern?: non-empty-string
 * }
 */
final class Repository
{
    #[XPath('@type')]
    public string $type = 'github';

    #[XPath('@uri')]
    public string $uri;

    #[XPath('@asset-pattern')]
    public string $assetPattern = '/^.*$/';

    /**
     * @param RepositoryArray $repositoryArray
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
