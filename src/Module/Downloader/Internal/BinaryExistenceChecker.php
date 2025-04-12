<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\Downloader\Internal;

use Internal\DLoad\Module\Common\Config\Embed\Binary;
use Internal\DLoad\Module\Common\OperatingSystem;

/**
 * Checks for existence of binary executables at specified paths.
 *
 * Handles operating system specific considerations for binary names,
 * such as .exe extensions on Windows.
 */
final class BinaryExistenceChecker
{
    /**
     * @param OperatingSystem $os Operating system detector
     */
    public function __construct(
        private readonly OperatingSystem $os,
    ) {}

    /**
     * Checks if a binary exists at the specified destination path.
     *
     * @param non-empty-string $destinationPath Directory path where binary should exist
     * @param Binary|null $binary Binary configuration to check
     * @return bool True if binary exists, false otherwise
     */
    public function exists(string $destinationPath, ?Binary $binary): bool
    {
        if ($binary === null) {
            return false;
        }

        $binaryPath = $this->buildBinaryPath($destinationPath, $binary);
        return $this->doesFileExist($binaryPath);
    }

    /**
     * Builds the full path to the binary, considering OS-specific extensions.
     *
     * @param non-empty-string $destinationPath Directory path
     * @param Binary $binary Binary configuration
     * @return string Full path to the binary
     */
    public function buildBinaryPath(string $destinationPath, Binary $binary): string
    {
        $destination = \rtrim(\str_replace('\\', '/', $destinationPath), '/');

        // Unix-based systems
        return "{$destination}/{$binary->name}{$this->os->getBinaryExtension()}";
    }

    /**
     * Wrapper for file_exists to make testing easier.
     *
     * @param string $path File path to check
     * @return bool True if file exists
     */
    protected function doesFileExist(string $path): bool
    {
        return \file_exists($path);
    }
}
