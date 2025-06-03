<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\Binary;

use Internal\DLoad\Module\Common\Stability;

/**
 * Contains --version output from a binary with parsed parts.
 *
 * @internal
 */
class Version
{
    /**
     * @param string $origin Source of the version string (e.g. binary name)
     * @param null|non-empty-string $version Parsed version string with suffix and stability
     * @param null|non-empty-string $postfix  Other information after the version
     */
    public function __construct(
        public readonly string $origin,
        public readonly ?string $version = null,
        public readonly ?string $postfix = null,
        public readonly ?Stability $stability = null,
    ) {}

    public static function empty(): self
    {
        return new self('');
    }
}
