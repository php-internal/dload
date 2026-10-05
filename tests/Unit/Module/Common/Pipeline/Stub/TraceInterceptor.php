<?php

declare(strict_types=1);

namespace Internal\DLoad\Tests\Unit\Module\Common\Pipeline\Stub;

use Internal\DLoad\Module\Common\Pipeline\Interceptor;

/**
 * Appends its label to the trace on the way in and on the way out.
 *
 * @implements Interceptor<\ArrayObject<int, string>, \ArrayObject<int, string>>
 */
final class TraceInterceptor implements Interceptor
{
    public function __construct(
        public readonly string $label,
    ) {}

    /**
     * @param \ArrayObject<int, string> $trace
     * @param callable(\ArrayObject<int, string>): \ArrayObject<int, string> $next
     * @return \ArrayObject<int, string>
     */
    public function handle(\ArrayObject $trace, callable $next): \ArrayObject
    {
        $trace[] = "{$this->label}:in";
        $result = $next($trace);
        $trace[] = "{$this->label}:out";

        return $result;
    }
}
