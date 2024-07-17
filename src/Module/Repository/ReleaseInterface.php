<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\Repository;

use Internal\DLoad\Module\Common\Stability;
use Internal\DLoad\Module\Repository\Internal\AssetsCollection;

interface ReleaseInterface
{
    public function getRepository(): RepositoryInterface;

    /**
     * Returns Composer's compatible "pretty" release version.
     *
     * @return non-empty-string
     */
    public function getName(): string;

    /**
     * Returns internal release tag version.
     *
     * @note this version may not be compatible with Composer's comparators.
     *
     * @return non-empty-string
     */
    public function getVersion(): string;

    public function getStability(): Stability;

    public function getAssets(): AssetsCollection;
}
