<?php

declare(strict_types=1);

namespace Internal\DLoad;

use Internal\DLoad\Module\Binary\BinaryProvider;
use Internal\DLoad\Module\Binary\Internal\BinaryProviderImpl;
use Internal\DLoad\Module\Common\Architecture;
use Internal\DLoad\Module\Common\Internal\Injection\ConfigLoader;
use Internal\DLoad\Module\Common\Internal\ObjectContainer;
use Internal\DLoad\Module\Common\OperatingSystem;
use Internal\DLoad\Module\Common\Stability;
use Internal\DLoad\Module\Repository\Internal\GitHub\Factory as GithubRepositoryFactory;
use Internal\DLoad\Module\Repository\RepositoryProvider;
use Internal\DLoad\Service\Container;

/**
 * Bootstraps the application by configuring the dependency container.
 *
 * Initializes the application container with configuration values and core services.
 * Serves as the entry point for the dependency injection setup.
 *
 * ```php
 * // Initialize application with default container and XML config
 * $container = Bootstrap::init()
 *     ->withConfig('dload.xml', $inputOptions, $inputArguments)
 *     ->finish();
 *
 * // Use container to access services
 * $downloader = $container->get(Downloader::class);
 * ```
 *
 * @internal
 */
final class Bootstrap
{
    private function __construct(
        private Container $container,
    ) {}

    /**
     * Creates a new bootstrap instance with the specified container.
     *
     * @param Container $container Dependency injection container (defaults to ObjectContainer)
     * @return self Bootstrap instance
     */
    public static function init(Container $container = new ObjectContainer()): self
    {
        return new self($container);
    }

    /**
     * Finalizes the bootstrap process and returns the configured container.
     *
     * @return Container Fully configured dependency container
     */
    public function finish(): Container
    {
        $c = $this->container;
        unset($this->container);

        return $c;
    }

    /**
     * Configures the container with XML configuration and input values.
     *
     * Registers core services and bindings for system architecture, OS detection,
     * and stability settings.
     *
     * @param non-empty-string|null $xml Path to XML file or raw XML content
     * @param array<string, mixed> $inputOptions Command-line options
     * @param array<string, mixed> $inputArguments Command-line arguments
     * @param array<string, string> $environment Environment variables
     * @return self Configured bootstrap instance
     * @throws \InvalidArgumentException When config file is not found
     * @throws \RuntimeException When config file cannot be read
     */
    public function withConfig(
        ?string $xml = null,
        array $inputOptions = [],
        array $inputArguments = [],
        array $environment = [],
    ): self {
        $args = [
            'env' => $environment,
            'inputArguments' => $inputArguments,
            'inputOptions' => $inputOptions,
        ];

        // XML config file
        $xml === null or $args['xml'] = $this->readXml($xml);

        // Register bindings
        $this->container->bind(ConfigLoader::class, $args);
        $this->container->bind(Architecture::class);
        $this->container->bind(OperatingSystem::class);
        $this->container->bind(Stability::class);
        $this->container->bind(
            RepositoryProvider::class,
            static fn(Container $container): RepositoryProvider => (new RepositoryProvider())
                ->addRepositoryFactory($container->get(GithubRepositoryFactory::class)),
        );
        $this->container->bind(
            BinaryProvider::class,
            static fn(Container $c): BinaryProvider => $c->get(BinaryProviderImpl::class),
        );

        return $this;
    }

    /**
     * Reads XML configuration from file or direct content string.
     *
     * @param non-empty-string $fileOrContent Path to XML file or raw XML content
     * @return string Parsed XML content
     * @throws \InvalidArgumentException When config file is not found
     * @throws \RuntimeException When config file cannot be read
     */
    private function readXml(string $fileOrContent): string
    {
        // Load content
        if (\str_starts_with($fileOrContent, '<?xml')) {
            $xml = $fileOrContent;
        } else {
            \file_exists($fileOrContent) or throw new \InvalidArgumentException('Config file not found.');
            $xml = \file_get_contents($fileOrContent);
            $xml === false and throw new \RuntimeException('Failed to read config file.');
        }

        // Validate Schema
        // todo

        return $xml;
    }
}
