<?php

declare(strict_types=1);

namespace Internal\DLoad\Service;

/**
 * Application container.
 *
 * @internal
 */
interface Container
{
    /**
     * @template T of object
     * @param class-string<T> $id
     * @param array $arguments Will be used if the object is created for the first time.
     * @return T
     *
     * @psalm-suppress MoreSpecificImplementedParamType, InvalidReturnType
     */
    public function get(string $id, array $arguments = []): object;

    /**
     * @param class-string $id
     *
     * @psalm-suppress MoreSpecificImplementedParamType
     */
    public function has(string $id): bool;

    /**
     * @template T of object
     * @param T $service
     * @param class-string<T>|null $id
     */
    public function set(object $service, ?string $id = null): void;

    /**
     * Create an object of the specified class without caching.
     *
     * @template T
     * @param class-string<T> $class
     * @return T
     */
    public function make(string $class, array $arguments = []): object;

    /**
     * Declare a factory or predefined arguments for the specified class.
     *
     * @template T of object
     * @param class-string<T> $id
     * @param array|\Closure(Container): T $binding
     */
    public function bind(string $id, \Closure|array|null $binding = null): void;

    public function destroy(): void;
}
