<?php

declare(strict_types=1);

namespace Internal\DLoad\Service;

/**
 * Class creates new instances of itself.
 *
 * @internal
 */
interface Factoriable
{
    public static function create(): static;
}
