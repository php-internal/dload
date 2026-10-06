<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\Downloader\Internal\AssetSelection\Rule;

use Internal\DLoad\Module\Common\Libc;
use Internal\DLoad\Module\Downloader\Internal\AssetSelection\AssetRule;
use Internal\DLoad\Module\Downloader\Internal\AssetSelection\Candidate;
use Internal\DLoad\Module\Downloader\Internal\AssetSelection\Selection;
use Psr\Container\ContainerInterface;

/**
 * Prefers the builds the host libc prefers, see {@see Libc::prefers()}.
 *
 * Ranks rather than removes: a static build runs whatever the libc, and some tools publish
 * only a musl build for Linux. The host libc comes from the container only when the assets
 * differ in libc: resolving it probes the file system.
 *
 * @internal
 * @psalm-internal Internal\DLoad\Module\Downloader
 */
final class LibcRule implements AssetRule
{
    public function __construct(
        private readonly ContainerInterface $container,
    ) {}

    public function select(Selection $selection, callable $next): Selection
    {
        $libcs = [];
        foreach ($selection->candidates as $candidate) {
            $libcs[($candidate->name->libc ?? Libc::Gnu)->value] = true;
        }

        // The host is probed only when there is a choice between libcs
        if (\count($libcs) < 2) {
            return $next($selection->rank('libc', static fn(): int => 0));
        }

        /** @var Libc $host */
        $host = $this->container->get(Libc::class);
        return $next($selection->prefer('libc', static fn(Candidate $candidate): bool => $host->prefers($candidate->name->libc)));
    }
}
