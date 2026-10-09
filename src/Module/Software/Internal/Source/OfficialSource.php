<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\Software\Internal\Source;

use Internal\DLoad\Info;
use Internal\DLoad\Module\Config\Schema\Embed\Software;
use Internal\DLoad\Module\Software\Internal\SoftwareSource;
use Internal\DLoad\Module\Software\Origin;
use Internal\DLoad\Module\Software\SoftwareCollection;

/**
 * Adds the software of the registry shipped with DLoad.
 *
 * @link resources/software.json
 *
 * @internal
 * @psalm-internal Internal\DLoad\Module\Software
 */
final class OfficialSource implements SoftwareSource
{
    /**
     * @param non-empty-string $file Path to the registry JSON file.
     */
    public function __construct(
        private readonly string $file = Info::ROOT_DIR . '/resources/software.json',
    ) {}

    public function collect(SoftwareCollection $collection, callable $next): SoftwareCollection
    {
        $json = (array) \json_decode(
            (string) \file_get_contents($this->file),
            true,
            16,
            JSON_THROW_ON_ERROR,
        );

        $software = \array_map(Software::fromArray(...), (array) ($json['software'] ?? []));

        return $next($collection->with(Origin::official(), ...$software));
    }
}
