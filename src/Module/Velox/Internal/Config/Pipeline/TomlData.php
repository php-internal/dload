<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\Velox\Internal\Config\Pipeline;

use Internal\Toml\Toml;

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
    /**
     * Creates a new immutable TOML data container.
     *
     * @param array<array-key, mixed> $data The configuration data array
     */
    public function __construct(
        private readonly array $data = [],
    ) {}

    /**
     * Creates a new TOML data instance from a TOML string.
     *
     * Parses the provided TOML content and returns a new immutable instance
     * containing the parsed configuration data.
     *
     * @param string $toml The TOML content to parse
     * @return self A new TomlData instance with the parsed data
     */
    public static function fromString(string $toml): self
    {
        return new self(Toml::parseToArray($toml));
    }

    /**
     * Merges another TOML data instance into this one.
     *
     * Performs a deep merge where arrays from the other instance are recursively
     * merged with arrays in this instance. Non-array values from the other instance
     * will overwrite values in this instance.
     *
     * @param TomlData $other The TOML data to merge into this instance
     * @return self A new TomlData instance with the merged data
     */
    public function merge(TomlData $other): self
    {
        $merged = $this->deepMerge($this->data, $other->data);
        return new self($merged);
    }

    /**
     * Sets a nested value using dot notation path.
     *
     * Creates a new immutable instance with the specified value set at the given path.
     * The path uses dot notation to access nested array keys (e.g., "debug.enabled").
     * If intermediate keys don't exist, they will be created as arrays.
     *
     * @param string $path The dot-notation path to the value (e.g., "roadrunner.ref")
     * @param mixed $value The value to set at the specified path
     * @return self A new TomlData instance with the updated value
     */
    public function set(string $path, mixed $value): self
    {
        $data = $this->data;
        $this->setNestedValue($data, $path, $value);
        return new self($data);
    }

    /**
     * Converts the internal data array to TOML string format.
     *
     * This is the main public API for serializing TOML data back to string format.
     * Delegates to the internal arrayToToml method for the actual conversion logic.
     *
     * @return string The TOML-formatted string representation of the data
     */
    public function toToml(): string
    {
        return (string) Toml::encode($this->data);
    }

    /**
     * Returns the raw configuration data array.
     *
     * Provides direct access to the internal data structure for cases where
     * array manipulation is needed instead of TOML string operations.
     *
     * @return array<string, mixed> The internal data array
     */
    public function getData(): array
    {
        return $this->data;
    }

    /**
     * Performs a deep merge of two arrays, recursively merging nested structures.
     *
     * When both arrays contain nested arrays at the same key, they are recursively
     * merged rather than the second array completely replacing the first. For scalar
     * values, the second array takes precedence (overlay behavior).
     *
     * @param array<string, mixed> $array1 The base array (lower precedence)
     * @param array<string, mixed> $array2 The overlay array (higher precedence)
     * @return array<string, mixed> The merged result with nested structures preserved
     */
    private function deepMerge(array $array1, array $array2): array
    {
        $merged = $array1; // Start with base array as foundation

        // Iterate through all keys in the second array (the overlay)
        foreach ($array2 as $key => $value) {
            // Check if both the current value and existing value are arrays
            // If so, recursively merge them instead of overwriting
            if (\is_array($value) && isset($merged[$key]) && \is_array($merged[$key])) {
                // Recursive merge for nested array structures
                // This preserves nested configuration while allowing overrides
                $merged[$key] = $this->deepMerge($merged[$key], $value);
            } else {
                // For scalar values or when no existing array exists, simply overwrite
                // This gives precedence to values from array2 (the overlay/remote config)
                /** @var mixed */
                $merged[$key] = $value;
            }
        }

        return $merged;
    }

    /**
     * Sets a value at a nested array path using dot notation.
     *
     * Creates intermediate array levels as needed to accommodate the full path.
     * If any intermediate level exists but is not an array, it will be converted
     * to an empty array. The final value overwrites any existing value at the path.
     *
     * @param array<string, mixed> $array The array to modify (passed by reference)
     * @param non-empty-string $path The dot-notation path (e.g., "section.subsection.key")
     * @param mixed $value The value to set at the specified path
     */
    private function setNestedValue(array &$array, string $path, mixed $value): void
    {
        // Split dot-notation path into individual keys
        // e.g., "github.plugins.logger" becomes ["github", "plugins", "logger"]
        $keys = \explode('.', $path);
        $current = &$array; // Reference to current position in nested structure

        // Navigate through the nested array structure, creating missing levels
        foreach ($keys as $key) {
            // Ensure the current level is an array (convert if needed)
            if (!\is_array($current)) {
                $current = [];
            }

            // Create the key if it doesn't exist (initialize as empty array)
            if (!isset($current[$key])) {
                $current[$key] = [];
            }

            // Move reference deeper into the nested structure
            $current = &$current[$key];
        }

        // Set the final value at the deepest nesting level
        // This overwrites any existing value at this path
        /** @var mixed */
        $current = $value;
    }
}
