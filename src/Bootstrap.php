<?php

declare(strict_types=1);

namespace Internal\DLoad;

use Internal\DLoad\Module\Common\Architecture;
use Internal\DLoad\Module\Common\Internal\Injection\ConfigLoader;
use Internal\DLoad\Module\Common\Internal\ObjectContainer;
use Internal\DLoad\Module\Common\OperatingSystem;
use Internal\DLoad\Module\Common\Stability;
use Internal\DLoad\Service\Container;

/**
 * Build the container based on the configuration.
 *
 * @internal
 */
final class Bootstrap
{
    private function __construct(
        private Container $container,
    ) {}

    public static function init(Container $container = new ObjectContainer()): self
    {
        return new self($container);
    }

    public function finish(): Container
    {
        $c = $this->container;
        unset($this->container);

        return $c;
    }

    /**
     * @param non-empty-string|null $xml File or XML content
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

        return $this;
    }

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
