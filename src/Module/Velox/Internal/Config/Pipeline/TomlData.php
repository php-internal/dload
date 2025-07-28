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
    /**
     * Creates a new immutable TOML data container.
     *
     * @param array<string, mixed> $data The configuration data array
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
        return $this->arrayToToml($this->data);
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
     * Simple TOML parser for basic configuration merging.
     *
     * @param string $toml TOML content
     * @return array<string, mixed> Parsed configuration
     */
    private function parseToml(string $toml): array
    {
        $result = [];
        $currentSection = null; // Track the current section context (e.g., "roadrunner" or "github.plugins")
        $lines = \explode("\n", $toml);

        foreach ($lines as $line) {
            $line = \trim($line);

            // Skip empty lines and comments (lines starting with #)
            if ($line === '' || \str_starts_with($line, '#')) {
                continue;
            }

            // Parse section headers like [roadrunner] or [github.plugins.logger]
            // These define the namespace for subsequent key-value pairs
            if (\preg_match('/^\[([^\]]+)\]$/', $line, $matches)) {
                $currentSection = $matches[1]; // Store the full section path
                continue;
            }

            // Parse key-value pairs in the format: key = value
            // Handles both quoted and unquoted values
            if (\preg_match('/^([^=]+)=(.+)$/', $line, $matches)) {
                $key = \trim($matches[1]);
                // Remove surrounding quotes and whitespace from values
                $value = \trim($matches[2], ' "\'');

                if ($currentSection === null) {
                    // Top-level key-value pair (no section)
                    $result[$key] = $value;
                } else {
                    // Nested key-value pair within a section
                    // Combine section path with key using dot notation
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
        $sections = []; // Collect associative arrays that will become TOML sections

        // First pass: output top-level scalar values and inline arrays
        // This ensures proper TOML structure with values before sections
        foreach ($data as $key => $value) {
            if (!\is_array($value)) {
                // Simple scalar value - output directly as key = "value"
                $toml .= "{$key} = \"{$value}\"\n";
            } elseif ($this->isInlineArray($value)) {
                // Sequential array - format as inline TOML array [item1, item2, ...]
                $toml .= $this->formatKeyValue((string) $key, $value);
            } else {
                // Associative array - defer to sections for proper TOML structure
                $sections[$key] = $value;
            }
        }

        // Add visual separator between top-level values and sections
        // This improves readability of the generated TOML
        if ($toml !== '' && !empty($sections)) {
            $toml .= "\n";
        }

        // Apply custom ordering to sections for consistent output
        // Prioritizes commonly used sections like 'roadrunner' first
        $orderedSections = $this->orderSections($sections);

        // Second pass: output all sections (associative arrays)
        // Each section becomes a [section.name] block in TOML
        foreach ($orderedSections as $sectionKey => $sectionValue) {
            $toml .= $this->sectionToToml($sectionKey, $sectionValue);
        }

        // Clean up any trailing whitespace for cleaner output
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
        // Define priority order for commonly used configuration sections
        // This ensures consistent output format with important sections first
        $priority = ['roadrunner', 'debug', 'log', 'github', 'gitlab'];
        $orderedSections = [];
        $remainingSections = $sections; // Copy to track unprocessed sections

        // First pass: add priority sections in their defined order
        // This maintains a predictable structure for configuration files
        foreach ($priority as $sectionKey) {
            if (isset($remainingSections[$sectionKey])) {
                $orderedSections[$sectionKey] = $remainingSections[$sectionKey];
                unset($remainingSections[$sectionKey]); // Remove from remaining list
            }
        }

        // Second pass: append any remaining sections that weren't in priority list
        // These will appear after priority sections in their original order
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

        // Analyze section structure to determine rendering approach
        // Check if this section contains nested associative arrays (subsections)
        $hasSubsections = false;
        foreach ($data as $value) {
            if (\is_array($value) && !$this->isInlineArray($value)) {
                $hasSubsections = true;
                break; // Early exit once we find a subsection
            }
        }

        if (!$hasSubsections) {
            // Simple flat section: only scalar values and inline arrays
            // Format: [section_name] followed by key=value pairs
            $toml .= "[{$sectionName}]\n";
            foreach ($data as $key => $value) {
                $toml .= $this->formatKeyValue((string) $key, $value);
            }
            $toml .= "\n"; // Add blank line after section
        } else {
            // Complex section with mixed content: both simple values and nested sections
            // Separate simple values from nested subsections for proper TOML structure
            $simpleValues = []; // Scalar values and inline arrays
            $subsections = [];  // Associative arrays that become nested sections

            foreach ($data as $subKey => $subValue) {
                if (\is_array($subValue) && !$this->isInlineArray($subValue)) {
                    // Associative array becomes a nested section
                    $subsections[$subKey] = $subValue;
                } else {
                    // Scalar or inline array stays in this section
                    $simpleValues[$subKey] = $subValue;
                }
            }

            // Output the main section header only if there are simple values
            // TOML requires section headers to have content, so we skip empty intermediate sections
            if (!empty($simpleValues)) {
                $toml .= "[{$sectionName}]\n";
                foreach ($simpleValues as $key => $value) {
                    $toml .= $this->formatKeyValue((string) $key, $value);
                }
                $toml .= "\n";
            }

            // Recursively render nested subsections with dotted notation
            // e.g., [section.subsection] format
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

        // Recursively separate content into simple values and nested subsections
        // This maintains proper TOML hierarchy for deeply nested configurations
        $simpleValues = []; // Scalar values and inline arrays for this section
        $subsections = [];  // Nested associative arrays for deeper sections

        foreach ($data as $key => $value) {
            if (\is_array($value) && !$this->isInlineArray($value)) {
                // Nested associative array - becomes a deeper subsection
                $subsections[$key] = $value;
            } else {
                // Scalar value or inline array - stays at current nesting level
                $simpleValues[$key] = $value;
            }
        }

        // Render section header and content based on what we found
        // Rules:
        // 1. If we have simple values, create the section header and add them
        // 2. If we have no subsections but the section exists, create empty section
        // 3. If we only have subsections and no simple values, skip this level's header
        if (!empty($simpleValues) || empty($subsections)) {
            $toml .= "[{$sectionPath}]\n";
            // Output all simple key-value pairs for this section level
            foreach ($simpleValues as $key => $value) {
                $toml .= $this->formatKeyValue((string) $key, $value);
            }
            $toml .= "\n"; // Blank line after section content
        }

        // Recursively render all nested subsections with extended path
        // Each subsection gets a dotted path like 'parent.child.grandchild'
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
            // Array values become inline TOML arrays: key = ["item1", "item2"]
            // Delegate to specialized array formatting method
            return "{$key} = " . $this->formatArrayValue($value) . "\n";
        }

        // Scalar values become quoted strings: key = "value"
        // All values are quoted for consistency and safety
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

        // Convert each array element to a TOML-compatible string representation
        foreach ($array as $item) {
            if (\is_array($item)) {
                // Nested arrays aren't natively supported in TOML inline arrays
                // Serialize to JSON string as a workaround for complex data structures
                $items[] = '"' . \json_encode($item) . '"';
            } else {
                // Simple scalar values are converted to strings and quoted
                // This ensures proper TOML syntax for all data types
                $items[] = '"' . (string) $item . '"';
            }
        }

        // Join all items with commas and wrap in square brackets
        // Result: ["item1", "item2", "item3"]
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
        // Empty arrays are treated as sections, not inline arrays
        // This prevents creating empty inline arrays and ensures proper TOML structure
        if (empty($array)) {
            return false;
        }

        // Check if array has sequential numeric keys starting from 0
        // Only truly sequential arrays (0, 1, 2, ...) become inline TOML arrays
        // Associative arrays with string keys become TOML sections instead
        return \array_keys($array) === \range(0, \count($array) - 1);
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
