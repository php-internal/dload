<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\Registry\Record;

/**
 * One page of a release listing as returned by a source.
 *
 * Besides the releases, the page tells whether the listing ends with it: the registry needs to
 * know that at the moment the page arrives, without requesting the next one to find out.
 */
final class ReleasePage
{
    /**
     * @param list<ReleaseRecord> $releases Releases of the page, newest first.
     * @param bool $last Whether the listing has no page after this one.
     */
    public function __construct(
        public readonly array $releases,
        public readonly bool $last,
    ) {}
}
