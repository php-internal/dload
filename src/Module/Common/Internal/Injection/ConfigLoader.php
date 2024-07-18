<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\Common\Internal\Injection;

use Internal\DLoad\Module\Common\Internal\Attribute\ConfigAttribute;
use Internal\DLoad\Module\Common\Internal\Attribute\Env;
use Internal\DLoad\Module\Common\Internal\Attribute\InputArgument;
use Internal\DLoad\Module\Common\Internal\Attribute\InputOption;
use Internal\DLoad\Module\Common\Internal\Attribute\PhpIni;
use Internal\DLoad\Module\Common\Internal\Attribute\XPath;
use Internal\DLoad\Module\Common\Internal\Attribute\XPathEmbedList;
use Internal\DLoad\Service\Logger;

/**
 * @internal
 */
final class ConfigLoader
{
    private \SimpleXMLElement|null $xml = null;

    /**
     * @psalm-suppress RiskyTruthyFalsyComparison
     */
    public function __construct(
        private readonly Logger $logger,
        private readonly array $env = [],
        private readonly array $inputArguments = [],
        private readonly array $inputOptions = [],
        ?string $xml = null,
    ) {
        if (\is_string($xml)) {
            // Check SimpleXML extension
            if (!\extension_loaded('simplexml')) {
                $logger->info('SimpleXML extension is not loaded.');
            } else {
                $this->xml = \simplexml_load_string($xml, options: \LIBXML_NOERROR) ?: null;
            }
        }
    }

    public function hydrate(object $config): void
    {
        // Read class properties
        $reflection = new \ReflectionObject($config);
        foreach ($reflection->getProperties() as $property) {
            $attributes = $property->getAttributes(ConfigAttribute::class, \ReflectionAttribute::IS_INSTANCEOF);
            if (\count($attributes) === 0) {
                continue;
            }

            $this->injectValue($config, $property, $attributes);
        }
    }

    /**
     * @param \ReflectionProperty $property
     * @param list<\ReflectionAttribute<ConfigAttribute>> $attributes
     */
    private function injectValue(object $config, \ReflectionProperty $property, array $attributes): void
    {
        foreach ($attributes as $attribute) {
            try {
                $attribute = $attribute->newInstance();

                /** @var mixed $value */
                $value = match (true) {
                    $attribute instanceof XPath => $this->getXPath($attribute),
                    $attribute instanceof XPathEmbedList => $this->getXPathEmbeddedList($attribute),
                    $attribute instanceof Env => $this->env[$attribute->name] ?? null,
                    $attribute instanceof InputOption => $this->inputOptions[$attribute->name] ?? null,
                    $attribute instanceof InputArgument => $this->inputArguments[$attribute->name] ?? null,
                    $attribute instanceof PhpIni => (static fn(string|false $value): ?string => match ($value) {
                        // Option does not exist or set to null
                        '', false => null,
                        default => $value,
                    })(\ini_get($attribute->option)),
                    default => null,
                };

                if (\in_array($value, [null, []], true)) {
                    continue;
                }

                // Cast value to the property type
                $type = $property->getType();

                /** @var mixed $result */
                $result = match (true) {
                    !$type instanceof \ReflectionNamedType => $value,
                    $type->allowsNull() && $value === '' => null,
                    $type->isBuiltin() => match ($type->getName()) {
                        'int' => (int) $value,
                        'float' => (float) $value,
                        'bool' => \filter_var($value, FILTER_VALIDATE_BOOLEAN),
                        'array' => match (true) {
                            \is_array($value) => $value,
                            \is_string($value) => \explode(',', $value),
                            default => [$value],
                        },
                        default => $value,
                    },
                    default => $value,
                };

                // todo Validation

                // Set the property value
                $property->setValue($config, $result);
                return;
            } catch (\Throwable $e) {
                $this->logger->exception($e, important: true);
            }
        }
    }

    private function getXPath(XPath $attribute): mixed
    {
        $value = $this->xml?->xpath($attribute->path);

        return \is_array($value) && \array_key_exists($attribute->key, $value)
            ? $value[$attribute->key]
            : null;
    }

    private function getXPathEmbeddedList(XPathEmbedList $attribute): array
    {
        $result = [];
        $value = $this->xml?->xpath($attribute->path);
        \is_array($value) or throw new \Exception(\sprintf('Invalid XPath `%s`', $attribute->path));

        foreach ($value as $xml) {
            \assert($xml instanceof \SimpleXMLElement);

            // Instantiate
            $item = new $attribute->class();

            $this->withXml($xml)->hydrate($item);
            $result[] = $item;
        }

        return $result;
    }

    private function withXml(\SimpleXMLElement $xml): self
    {
        $self = clone $this;
        $self->xml = $xml;
        return $self;
    }
}
