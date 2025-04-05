<?php

declare(strict_types=1);

namespace Internal\DLoad\Service;

/**
 * Application dependency injection container.
 *
 * Manages service instances throughout the application lifecycle.
 *
 * ```php
 * // Retrieving a service instance
 * $downloader = $container->get(Downloader::class);
 * ```
 *
 * @internal
 */
interface Container extends Destroyable
{
    /**
     * Retrieves a service from the container.
     *
     * If the service is requested for the first time, it will be instantiated and persisted for future requests.
     *
     * @template T
     * @param class-string<T> $id Service identifier
     * @param array $arguments Constructor arguments used only on first instantiation
     * @return T The requested service instance
     *
     * @psalm-suppress MoreSpecificImplementedParamType, InvalidReturnType
     */
    public function get(string $id, array $arguments = []): object;

    /**
     * Checks if the service is registered in the container.
     *
     * It means that the container has a cached service instance or a binding.
     *
     * @param class-string $id Service identifier
     * @return bool Whether the service is available
     *
     * @psalm-suppress MoreSpecificImplementedParamType
     */
    public function has(string $id): bool;

    /**
     * Registers an existing service instance in the container.
     *
     * @template T of object
     * @param T $service Service instance to register
     * @param class-string<T>|null $id Optional service identifier (defaults to object's class)
     */
    public function set(object $service, ?string $id = null): void;

    /**
     * Creates a new instance without storing it in the container.
     *
     * @template T
     * @param class-string<T> $class Class to instantiate
     * @param array $arguments Constructor arguments
     * @return T Newly created instance
     */
    public function make(string $class, array $arguments = []): object;

    /**
     * Configures how a service should be instantiated.
     *
     * @template T of object
     * @param class-string<T> $id Service identifier
     * @param array|\Closure(Container): T $binding Factory function or constructor arguments
     */
    public function bind(string $id, \Closure|array|null $binding = null): void;
}
