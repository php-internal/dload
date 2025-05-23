<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\Binary\Internal;

use Internal\DLoad\Module\Binary\Exception\BinaryExecutionException;
use Internal\DLoad\Module\Common\FileSystem\Path;

/**
 * Executes binary commands and captures their output.
 *
 * @internal
 */
final class BinaryExecutor
{
    /**
     * Executes a binary with the specified command and returns the output.
     *
     * @param Path $binaryPath Full path to binary executable
     * @param string $command Command argument(s) to execute
     * @return string Command output
     * @throws BinaryExecutionException If execution fails
     */
    public function execute(Path $binaryPath, string $command): string
    {
        // Escape command for shell execution
        $escapedPath = \escapeshellarg((string) $binaryPath);

        // Execute the command and capture output
        $output = [];
        $returnCode = 0;

        // Execute with both stdout and stderr redirected to output
        \exec("$escapedPath $command 2>&1", $output, $returnCode);

        // If command failed, throw exception
        if ($returnCode !== 0) {
            throw new BinaryExecutionException(
                \sprintf(
                    'Failed to execute binary "%s" with command "%s". Exit code: %d. Output: %s',
                    $binaryPath,
                    $command,
                    $returnCode,
                    \implode("\n", $output),
                ),
            );
        }

        // Return combined output
        return \implode("\n", $output);
    }
}
