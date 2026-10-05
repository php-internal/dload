<?php

declare(strict_types=1);

namespace Internal\DLoad\Tests\Unit\Module\Common\Pipeline\Stub;

use Internal\DLoad\Module\Common\Pipeline\Interceptor;

/**
 * Ends the chain without calling the rest of it.
 *
 * @implements Interceptor<\ArrayObject<int, string>, \ArrayObject<int, string>>
 */
final class HaltInterceptor implements Interceptor
{
    /**
     * @param \ArrayObject<int, string> $trace
     * @return \ArrayObject<int, string>
     */
    public function handle(\ArrayObject $trace, callable $next): \ArrayObject
    {
        $trace[] = 'halt';

        return $trace;
    }
}
