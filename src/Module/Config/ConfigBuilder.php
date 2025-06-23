<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\Config;

use Internal\DLoad\Module\Common\Config\Action\Download as DownloadConfig;

/**
 * Builder for creating DLoad XML configuration files.
 *
 * Provides a fluent interface for constructing properly formatted XML configuration
 * with schema validation and proper escaping.
 *
 * ```php
 *  $builder = new ConfigBuilder();
 *  $xml = $builder
 *      ->withSchemaUrl('https://example.com/schema.xsd')
 *      ->withTempDir('./temp')
 *      ->addDownloadAction($downloadConfig)
 *      ->build();
 * ```
 *
 * @internal
 */
final class ConfigBuilder
{
    private const DEFAULT_SCHEMA_URL = 'https://raw.githubusercontent.com/php-internal/dload/refs/heads/1.x/dload.xsd';
    private const INDENT = '    ';

    private ?string $schemaUrl = null;
    private ?string $tempDir = null;

    /** @var list<DownloadConfig> */
    private array $downloadActions = [];

    /**
     * Sets the XML schema URL for validation.
     *
     * @param non-empty-string $url Schema URL
     * @return $this
     */
    public function withSchemaUrl(string $url): self
    {
        $this->schemaUrl = $url;
        return $this;
    }

    /**
     * Sets the temporary directory for downloads.
     *
     * @param non-empty-string $tempDir Temporary directory path
     * @return $this
     */
    public function withTempDir(string $tempDir): self
    {
        $this->tempDir = $tempDir;
        return $this;
    }

    /**
     * Adds a download action to the configuration.
     *
     * @param DownloadConfig $action Download action configuration
     * @return $this
     */
    public function addDownloadAction(DownloadConfig $action): self
    {
        $this->downloadActions[] = $action;
        return $this;
    }

    /**
     * Adds multiple download actions to the configuration.
     *
     * @param list<DownloadConfig> $actions Download action configurations
     * @return $this
     */
    public function withDownloadActions(array $actions): self
    {
        foreach ($actions as $action) {
            $this->addDownloadAction($action);
        }
        return $this;
    }

    /**
     * Builds the complete XML configuration.
     *
     * @return string Complete XML configuration content
     */
    public function build(): string
    {
        $xml = [];
        $xml[] = '<?xml version="1.0" encoding="UTF-8"?>';
        $xml[] = $this->buildRootElement();
        $xml[] = $this->buildActionsSection();
        $xml[] = '</dload>';

        return \implode("\n", \array_filter($xml)) . "\n";
    }

    /**
     * Builds the root dload element with attributes.
     *
     * @return string Root element opening tag with attributes
     */
    private function buildRootElement(): string
    {
        $attributes = [
            'xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"',
            'xsi:noNamespaceSchemaLocation="' . $this->escapeXml($this->schemaUrl ?? self::DEFAULT_SCHEMA_URL) . '"',
        ];

        if ($this->tempDir !== null) {
            $attributes[] = 'temp-dir="' . $this->escapeXml($this->tempDir) . '"';
        }

        return '<dload ' . \implode(' ', $attributes) . '>';
    }

    /**
     * Builds the actions section with download elements.
     *
     * @return string|null Actions section XML or null if no actions
     */
    private function buildActionsSection(): ?string
    {
        if ($this->downloadActions === []) {
            return null;
        }

        $xml = [];
        $xml[] = self::INDENT . '<actions>';

        foreach ($this->downloadActions as $action) {
            $xml[] = $this->buildDownloadElement($action);
        }

        $xml[] = self::INDENT . '</actions>';

        return \implode("\n", $xml);
    }

    /**
     * Builds a single download element.
     *
     * @param DownloadConfig $action Download action configuration
     * @return string Download element XML
     */
    private function buildDownloadElement(DownloadConfig $action): string
    {
        $attributes = ['software="' . $this->escapeXml($action->software) . '"'];

        if ($action->version !== null) {
            $attributes[] = 'version="' . $this->escapeXml($action->version) . '"';
        }

        if ($action->extractPath !== null) {
            $attributes[] = 'extract-path="' . $this->escapeXml($action->extractPath) . '"';
        }

        if ($action->type !== null) {
            $attributes[] = 'type="' . $this->escapeXml($action->type->value) . '"';
        }

        return self::INDENT . self::INDENT . '<download ' . \implode(' ', $attributes) . ' />';
    }

    /**
     * Escapes special XML characters in attribute values.
     *
     * @param string $value Value to escape
     * @return string Escaped value safe for XML attributes
     */
    private function escapeXml(string $value): string
    {
        return \htmlspecialchars($value, \ENT_QUOTES | \ENT_XML1, 'UTF-8');
    }
}
