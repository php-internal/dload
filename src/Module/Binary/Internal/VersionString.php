<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\Binary\Internal;

use Internal\DLoad\Module\Common\Stability;

/**
 * Contains --version output from a binary with parsed parts.
 *
 * @internal
 */
class VersionString
{
    /**
     * @param string $origin Source of the version string (e.g. binary name)
     * @param null|non-empty-string $version Parsed version string
     * @param null|non-empty-string $suffix Optional feature suffix or stability
     */
    public function __construct(
        public readonly string $origin,
        public readonly ?string $version = null,
        public readonly ?string $suffix = null,
        public readonly ?Stability $stability = null,
    ) {}

    public static function empty(): self
    {
        return new self('');
    }
}
