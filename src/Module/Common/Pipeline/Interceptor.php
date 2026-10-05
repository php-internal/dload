<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\Common\Pipeline;

/**
 * Father interface for all interceptors.
 *
 * Extend it with an interface that declares the interceptor method of a concrete pipeline.
 *
 * @template TInput
 * @template-covariant TOutput
 *
 * @internal
 */
interface Interceptor {}
