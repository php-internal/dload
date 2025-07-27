<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\Velox\Internal\Config;

use Internal\DLoad\Module\Velox\Exception\Config as ConfigException;

/**
 * Simple TOML merger for combining local and remote configurations.
 *
 * @internal
 * @psalm-internal Internal\DLoad\Module\Velox
 */
final class TomlMerger
{
    /**
     * Merges remote TOML configuration into a local base configuration.
     *
     * Remote plugins extend/override local plugins while preserving other sections.
     *
     * @param string $localToml Base TOML configuration
     * @param string $remoteToml Remote TOML configuration with plugins
     * @return string Merged TOML configuration
     * @throws ConfigException When merging fails
     */
    public function merge(string $localToml, string $remoteToml): string
    {
        try {
            $localData = $this->parseToml($localToml);
            $remoteData = $this->parseToml($remoteToml);

            // Merge github.plugins sections
            if (isset($remoteData['github']['plugins'])) {
                $localData['github']['plugins'] = \array_merge(
                    $localData['github']['plugins'] ?? [],
                    $remoteData['github']['plugins'],
                );
            }

            // Preserve roadrunner version from local unless remote specifies one
            if (isset($remoteData['roadrunner']['ref']) && !isset($localData['roadrunner']['ref'])) {
                $localData['roadrunner']['ref'] = $remoteData['roadrunner']['ref'];
            }

            return $this->arrayToToml($localData);
        } catch (\Throwable $e) {
            throw new ConfigException(
                'Failed to merge TOML configurations: ' . $e->getMessage(),
                previous: $e,
            );
        }
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
     * Sets a nested array value using dot notation.
     *
     * @param array<string, mixed> $array Target array
     * @param string $path Dot-separated path
     * @param mixed $value Value to set
     */
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
}
