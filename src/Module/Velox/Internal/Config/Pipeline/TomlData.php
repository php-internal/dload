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
        $sections = [];

        // First output top-level keys (non-arrays and inline arrays)
        foreach ($data as $key => $value) {
            if (!\is_array($value)) {
                $toml .= "{$key} = \"{$value}\"\n";
            } elseif ($this->isInlineArray($value)) {
                $toml .= $this->formatKeyValue((string) $key, $value);
            } else {
                // Collect sections for later
                $sections[$key] = $value;
            }
        }

        // Add separator between top-level values and sections
        if ($toml !== '' && !empty($sections)) {
            $toml .= "\n";
        }

        // Order sections with roadrunner first, then debug, logs, github, gitlab, etc.
        $orderedSections = $this->orderSections($sections);

        // Then output sections (associative arrays)
        foreach ($orderedSections as $sectionKey => $sectionValue) {
            $toml .= $this->sectionToToml($sectionKey, $sectionValue);
        }

        // Remove trailing whitespace
        return \rtrim($toml);
    }

    /**
     * Orders sections with priority: roadrunner first, then debug, logs, github, gitlab, etc.
     *
     * @param array<string, mixed> $sections Sections to order
     * @return array<string, mixed> Ordered sections
     */
    private function orderSections(array $sections): array
    {
        $priority = ['roadrunner', 'debug', 'log', 'github', 'gitlab'];
        $orderedSections = [];
        $remainingSections = $sections;

        // First add priority sections in order
        foreach ($priority as $sectionKey) {
            if (isset($remainingSections[$sectionKey])) {
                $orderedSections[$sectionKey] = $remainingSections[$sectionKey];
                unset($remainingSections[$sectionKey]);
            }
        }

        // Then add any remaining sections
        foreach ($remainingSections as $sectionKey => $sectionValue) {
            $orderedSections[$sectionKey] = $sectionValue;
        }

        return $orderedSections;
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

        // Check if this section has subsections (associative arrays, not inline arrays)
        $hasSubsections = false;
        foreach ($data as $value) {
            if (\is_array($value) && !$this->isInlineArray($value)) {
                $hasSubsections = true;
                break;
            }
        }

        if (!$hasSubsections) {
            // Simple section with key-value pairs (and possibly inline arrays)
            $toml .= "[{$sectionName}]\n";
            foreach ($data as $key => $value) {
                $toml .= $this->formatKeyValue((string) $key, $value);
            }
            $toml .= "\n";
        } else {
            // Handle mixed content: simple values and subsections
            $simpleValues = [];
            $subsections = [];

            foreach ($data as $subKey => $subValue) {
                if (\is_array($subValue) && !$this->isInlineArray($subValue)) {
                    $subsections[$subKey] = $subValue;
                } else {
                    $simpleValues[$subKey] = $subValue;
                }
            }

            // Only output the section header if there are simple values
            // If there are only subsections, skip the intermediate section header
            if (!empty($simpleValues)) {
                $toml .= "[{$sectionName}]\n";
                foreach ($simpleValues as $key => $value) {
                    $toml .= $this->formatKeyValue((string) $key, $value);
                }
                $toml .= "\n";
            }

            // Output subsections directly
            foreach ($subsections as $subKey => $subValue) {
                $toml .= $this->renderNestedSection("{$sectionName}.{$subKey}", $subValue);
            }
        }

        return $toml;
    }

    /**
     * Renders a nested section recursively.
     *
     * @param string $sectionPath Full section path (e.g., "github.plugins.logger")
     * @param array<string, mixed> $data Section data
     * @return string TOML section content
     */
    private function renderNestedSection(string $sectionPath, array $data): string
    {
        $toml = '';

        // Separate simple values from subsections
        $simpleValues = [];
        $subsections = [];

        foreach ($data as $key => $value) {
            if (\is_array($value) && !$this->isInlineArray($value)) {
                $subsections[$key] = $value;
            } else {
                $simpleValues[$key] = $value;
            }
        }

        // Add section header if there are simple values OR if this section has no subsections
        // (meaning it's an empty section that should be rendered)
        if (!empty($simpleValues) || empty($subsections)) {
            $toml .= "[{$sectionPath}]\n";
            foreach ($simpleValues as $key => $value) {
                $toml .= $this->formatKeyValue((string) $key, $value);
            }
            $toml .= "\n";
        }

        // Render subsections
        foreach ($subsections as $key => $value) {
            $toml .= $this->renderNestedSection("{$sectionPath}.{$key}", $value);
        }

        return $toml;
    }

    /**
     * Formats a key-value pair for TOML output.
     *
     * @param string $key The key
     * @param mixed $value The value
     * @return string Formatted TOML key-value pair
     */
    private function formatKeyValue(string $key, mixed $value): string
    {
        if (\is_array($value)) {
            // For array values, render them as inline arrays
            return "{$key} = " . $this->formatArrayValue($value) . "\n";
        }

        return "{$key} = \"{$value}\"\n";
    }

    /**
     * Formats an array value for TOML output.
     *
     * @param array<mixed> $array The array to format
     * @return string Formatted TOML array
     */
    private function formatArrayValue(array $array): string
    {
        $items = [];
        foreach ($array as $item) {
            if (\is_array($item)) {
                // Nested arrays are not typically supported in TOML inline arrays
                // Convert to string representation
                $items[] = '"' . \json_encode($item) . '"';
            } else {
                $items[] = '"' . (string) $item . '"';
            }
        }

        return '[' . \implode(', ', $items) . ']';
    }

    /**
     * Determines if an array should be rendered as an inline array rather than a section.
     *
     * @param array<mixed> $array The array to check
     * @return bool True if it should be an inline array
     */
    private function isInlineArray(array $array): bool
    {
        // If empty, treat as section (will render as empty section)
        if (empty($array)) {
            return false;
        }

        // If all keys are numeric (sequential), it's an inline array
        return \array_keys($array) === \range(0, \count($array) - 1);
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
