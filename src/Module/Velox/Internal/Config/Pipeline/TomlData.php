<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\Velox\Internal\Config\Pipeline;

/**
 * Immutable TOML data container for pipeline processing.
 *
 * Provides methods for merging, setting values, and converting
 * between array and TOML string representations.
 * Consolidates all TOML parsing and formatting functionality.
 *
 * @internal
 * @psalm-internal Internal\DLoad\Module\Velox
 */
final class TomlData
{
    public function __construct(
        private readonly array $data = [],
    ) {}

    public static function fromString(string $toml): self
    {
        $instance = new self();
        $data = $instance->parseToml($toml);
        return new self($data);
    }

    /**
     * Merges remote TOML configuration into local base configuration.
     *
     * @param string $localToml Base TOML configuration
     * @param string $remoteToml Remote TOML configuration with plugins
     * @return string Merged TOML configuration
     */
    public static function mergeTomlStrings(string $localToml, string $remoteToml): string
    {
        $local = self::fromString($localToml);
        $remote = self::fromString($remoteToml);
        return $local->merge($remote)->toToml();
    }

    public function merge(TomlData $other): self
    {
        $merged = $this->deepMerge($this->data, $other->data);
        return new self($merged);
    }

    public function set(string $path, mixed $value): self
    {
        $data = $this->data;
        $this->setNestedValue($data, $path, $value);
        return new self($data);
    }

    public function toToml(): string
    {
        return $this->arrayToToml($this->data);
    }

    public function getData(): array
    {
        return $this->data;
    }

    /**
     * Simple TOML parser for basic configuration merging.
     *
     * @param string $toml TOML content
     * @return array<string, mixed> Parsed configuration
     */
    private function parseToml(string $toml): array
    {
        $result = [];
        $currentSection = null;
        $lines = \explode("\n", $toml);

        foreach ($lines as $line) {
            $line = \trim($line);

            // Skip empty lines and comments
            if ($line === '' || \str_starts_with($line, '#')) {
                continue;
            }

            // Section headers like [roadrunner] or [github.plugins.logger]
            if (\preg_match('/^\[([^\]]+)\]$/', $line, $matches)) {
                $currentSection = $matches[1];
                continue;
            }

            // Key-value pairs
            if (\preg_match('/^([^=]+)=(.+)$/', $line, $matches)) {
                $key = \trim($matches[1]);
                $value = \trim($matches[2], ' "\'');

                if ($currentSection === null) {
                    $result[$key] = $value;
                } else {
                    $this->setNestedValue($result, $currentSection . '.' . $key, $value);
                }
            }
        }

        return $result;
    }

    /**
     * Converts array back to TOML format.
     *
     * @param array<string, mixed> $data Configuration data
     * @return string TOML content
     */
    private function arrayToToml(array $data): string
    {
        $toml = '';

        // First output top-level keys
        foreach ($data as $key => $value) {
            if (!\is_array($value)) {
                $toml .= "{$key} = \"{$value}\"\n";
            }
        }

        if ($toml !== '') {
            $toml .= "\n";
        }

        // Then output sections
        foreach ($data as $sectionKey => $sectionValue) {
            if (\is_array($sectionValue)) {
                $toml .= $this->sectionToToml($sectionKey, $sectionValue);
            }
        }

        return $toml;
    }

    /**
     * Converts a section to TOML format.
     *
     * @param string $sectionName Section name
     * @param array<string, mixed> $data Section data
     * @return string TOML section content
     */
    private function sectionToToml(string $sectionName, array $data): string
    {
        $toml = '';

        // Check if this section has subsections
        $hasSubsections = false;
        foreach ($data as $value) {
            if (\is_array($value)) {
                $hasSubsections = true;
                break;
            }
        }

        if (!$hasSubsections) {
            // Simple section with key-value pairs
            $toml .= "[{$sectionName}]\n";
            foreach ($data as $key => $value) {
                $toml .= "{$key} = \"{$value}\"\n";
            }
            $toml .= "\n";
        } else {
            // Section with subsections (like github.plugins)
            foreach ($data as $subKey => $subValue) {
                if (\is_array($subValue)) {
                    $toml .= "[{$sectionName}.{$subKey}]\n";
                    foreach ($subValue as $key => $value) {
                        $toml .= "{$key} = \"{$value}\"\n";
                    }
                    $toml .= "\n";
                } else {
                    $toml .= "[{$sectionName}]\n";
                    $toml .= "{$subKey} = \"{$subValue}\"\n";
                    $toml .= "\n";
                }
            }
        }

        return $toml;
    }

    private function deepMerge(array $array1, array $array2): array
    {
        $merged = $array1;

        foreach ($array2 as $key => $value) {
            if (\is_array($value) && isset($merged[$key]) && \is_array($merged[$key])) {
                $merged[$key] = $this->deepMerge($merged[$key], $value);
            } else {
                $merged[$key] = $value;
            }
        }

        return $merged;
    }

    private function setNestedValue(array &$array, string $path, mixed $value): void
    {
        $keys = \explode('.', $path);
        $current = &$array;

        foreach ($keys as $key) {
            if (!\is_array($current)) {
                $current = [];
            }
            if (!isset($current[$key])) {
                $current[$key] = [];
            }
            $current = &$current[$key];
        }

        $current = $value;
    }
}
