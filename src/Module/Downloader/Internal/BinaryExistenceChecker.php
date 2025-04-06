<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\Downloader\Internal;

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
     * @param string $destinationPath Directory path where binary should exist
     * @param string|null $binaryName Name of the binary executable to check
     * @return bool True if binary exists, false otherwise
     */
    public function exists(string $destinationPath, ?string $binaryName): bool
    {
        if ($binaryName === null) {
            return false;
        }

        $binaryPath = $this->buildBinaryPath($destinationPath, $binaryName);

        return $this->doesFileExist($binaryPath);
    }

    /**
     * Builds the full path to the binary, considering OS-specific extensions.
     *
     * @param string $destinationPath Directory path
     * @param string $binaryName Binary name
     * @return string Full path to the binary
     */
    public function buildBinaryPath(string $destinationPath, string $binaryName): string
    {
        $destination = \rtrim($destinationPath, '/\\');

        // Unix-based systems
        return "{$destination}/{$binaryName}{$this->os->getBinaryExtension()}";
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
