<?php

declare(strict_types=1);

namespace Internal\DLoad\Tests\Unit\Module\Downloader\Internal\AssetSelection\Stub;

use Internal\DLoad\Module\Common\Libc;
use Psr\Container\ContainerInterface;

/**
 * Container that only knows the host libc and counts how often it is asked for.
 */
final class LibcContainer implements ContainerInterface
{
    /** @var int<0, max> */
    public int $requests = 0;

    public function __construct(
        private readonly Libc $libc,
    ) {}

    public function get(string $id): Libc
    {
        $id === Libc::class or throw new \LogicException("Unexpected request for `$id`.");
        ++$this->requests;

        return $this->libc;
    }

    public function has(string $id): bool
    {
        return $id === Libc::class;
    }
}
