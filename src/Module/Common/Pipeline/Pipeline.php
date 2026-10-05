<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\Common\Pipeline;

use Internal\DLoad\Module\Common\Pipeline\Interceptor as TInterceptor;

/**
 * Processor for interceptors chain.
 *
 * Interceptors run in the order they are given. Each one receives the input and the rest of
 * the chain as `$next`, and the last handler ends the chain.
 *
 * ```php
 * $pipeline = Pipeline::prepare(...$interceptors)->with(
 *     static fn(Input $input): Output => new Output($input),
 *     'process',
 * );
 * $output = $pipeline($input);
 * ```
 *
 * @template-covariant TClass of TInterceptor
 * @template TInput
 * @template-covariant TOutput of mixed
 *
 * @psalm-immutable
 * @internal
 *
 * @psalm-suppress PropertyNotSetInConstructor $method and $last are set later via {@see self::with()}.
 */
final class Pipeline
{
    /** @var non-empty-string */
    private string $method;

    /** @var callable(TInput): TOutput */
    private mixed $last;

    /** @var int<0, max> Current interceptor key */
    private int $current = 0;

    /**
     * @param list<TInterceptor> $interceptors
     */
    private function __construct(
        private readonly array $interceptors,
    ) {}

    /**
     * Create a pipeline with given interceptors.
     *
     * @template-covariant TInt of TInterceptor
     * @template TIn
     * @template-covariant TOut
     * @param TInterceptor ...$interceptors Instantiated interceptors, in the order they run.
     * @return self<TInt, TIn, TOut>
     *
     * @note Make sure that interceptors implement the same interface.
     * @psalm-suppress InvalidTemplateParam, UndefinedDocblockClass, InvalidReturnType, InvalidReturnStatement
     */
    public static function prepare(TInterceptor ...$interceptors): self
    {
        return new self(\array_values($interceptors));
    }

    /**
     * @param non-empty-string $method Method name of the all interceptors.
     *
     * @return callable(object): TOutput
     * @psalm-suppress InvalidReturnType, InvalidReturnStatement, MixedPropertyTypeCoercion
     */
    public function with(callable $last, string $method): callable
    {
        $new = clone $this;

        $new->last = $last;
        $new->method = $method;

        return $new;
    }

    /**
     * Must be used after {@see self::with()} method.
     *
     * @param TInput $input Input value for the first interceptor.
     *
     * @return TOutput
     * @psalm-suppress ImpureFunctionCall, MixedReturnStatement
     */
    public function __invoke(object $input): mixed
    {
        $interceptor = $this->interceptors[$this->current] ?? null;

        if ($interceptor === null) {
            return ($this->last)($input);
        }

        $next = $this->next();

        return $interceptor->{$this->method}($input, $next);
    }

    private function next(): self
    {
        $new = clone $this;
        ++$new->current;

        return $new;
    }
}
