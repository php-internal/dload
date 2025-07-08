<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\Common\Internal;

use Internal\DLoad\Module\Common\Internal\Injection\ConfigLoader;
use Internal\DLoad\Service\Container;
use Internal\DLoad\Service\Factoriable;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use Yiisoft\Injector\Injector;

/**
 * Simple dependency injection container.
 *
 * Provides service creation and caching with autowiring capabilities.
 * Automatically loads configuration for config classes.
 *
 * @internal
 */
final class ObjectContainer implements Container, ContainerInterface
{
    /** @var array<class-string, object> */
    private array $cache = [];

    /** @var array<class-string, array|\Closure(Container): object> */
    private array $factory = [];

    private readonly Injector $injector;

    /**
     * @psalm-suppress PropertyTypeCoercion
     */
    public function __construct()
    {
        $this->injector = (new Injector($this))->withCacheReflections(false);
        $this->cache[Injector::class] = $this->injector;
        $this->cache[self::class] = $this;
        $this->cache[Container::class] = $this;
        $this->cache[ContainerInterface::class] = $this;
    }

    public function get(string $id, array $arguments = []): object
    {
        /** @psalm-suppress InvalidReturnStatement */
        return $this->cache[$id] ??= $this->make($id, $arguments);
    }

    public function has(string $id): bool
    {
        return \array_key_exists($id, $this->cache) || \array_key_exists($id, $this->factory);
    }

    public function set(object $service, ?string $id = null): void
    {
        \assert($id === null || $service instanceof $id, "Service must be instance of {$id}.");
        $this->cache[$id ?? \get_class($service)] = $service;
    }

    public function make(string $class, array $arguments = []): object
    {
        $binding = $this->factory[$class] ?? null;

        if ($binding instanceof \Closure) {
            $result = $this->injector->invoke($binding);
        } else {
            try {
                $result = $this->injector->make($class, \array_merge((array) $binding, $arguments));
            } catch (\Throwable $e) {
                throw new class("Unable to create object of class $class.", previous: $e) extends \RuntimeException implements NotFoundExceptionInterface {};
            }
        }

        \assert($result instanceof $class, "Created object must be instance of {$class}.");

        // Detect related types
        // Configs
        if (\str_starts_with($class, 'Internal\\DLoad\\Module\\Config\\Schema\\')) {
            // Hydrate config
            /** @var ConfigLoader $configLoader */
            $configLoader = $this->get(ConfigLoader::class);
            $configLoader->hydrate($result);
        }

        return $result;
    }

    /**
     * @template T
     * @param class-string<T> $id Service identifier
     * @param null|class-string<T>|array<string, mixed>|\Closure(Container): T $binding
     */
    public function bind(string $id, \Closure|string|array|null $binding = null): void
    {
        if (\is_string($binding)) {
            \class_exists($binding) or throw new \InvalidArgumentException(
                "Class `$binding` does not exist.",
            );

            /** @var class-string<T> $binding */
            $binding = \is_a($binding, Factoriable::class, true)
                ? fn(): object => $this->injector->invoke([$binding, 'create'])
                : fn(): object => $this->injector->make($binding);
        }

        if ($binding !== null) {
            $this->factory[$id] = $binding;
            return;
        }

        (\class_exists($id) && \is_a($id, Factoriable::class, true)) or throw new \InvalidArgumentException(
            "Class `$id` must have a factory or be a factory itself and implement `Factoriable`.",
        );

        /** @var T $object */
        $object = $id::create(...);
        $this->factory[$id] = $object;
    }

    public function destroy(): void
    {
        unset($this->cache, $this->factory, $this->injector);
    }
}
