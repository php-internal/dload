<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\Downloader;

final class Progress
{
    public function __construct(
        public readonly int $total = 100,
        public readonly int $current = 0,
        public readonly string $message = '',
    ) {}
}
