<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\Binary\Internal;

use Internal\DLoad\Module\Binary\Exception\BinaryExecutionException;
use Internal\DLoad\Service\Logger;
use Internal\Path;

/**
 * Executes binary commands and captures their output.
 *
 * @internal
 */
final class BinaryExecutor
{
    public function __construct(
        private readonly Logger $logger,
    ) {}

    /**
     * Executes a binary with the specified command and returns the output.
     *
     * @param Path $binaryPath Full path to binary executable
     * @param string ...$arguments Each one reaches the binary as is: quotes, pipes and other shell
     *        syntax are not interpreted.
     * @return list<string> Command output
     * @throws BinaryExecutionException If execution fails
     */
    public function execute(Path $binaryPath, string ...$arguments): array
    {
        // JSON keeps the boundaries of arguments with spaces visible
        $command = (string) \json_encode($arguments, \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE);
        $this->logger->debug('Executing %s with arguments %s', (string) $binaryPath, $command);

        // An array command starts the binary without a shell: escapeshellarg() on Windows would replace
        // `"`, `%` and `!` in arguments with spaces. The closed stdin keeps an interactive binary from waiting.
        // On Windows a binary that cannot start (a truncated file, an ELF) makes proc_open() warn and return false.
        $process = @\proc_open(
            [(string) $binaryPath, ...$arguments],
            [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['redirect', 1]],
            $pipes,
        );
        $process === false and throw new BinaryExecutionException(\sprintf(
            'Failed to execute binary "%s" with arguments %s: %s',
            $binaryPath,
            $command,
            \error_get_last()['message'] ?? 'the process did not start.',
        ));

        \fclose($pipes[0]);
        $stdout = (string) \stream_get_contents($pipes[1]);
        \fclose($pipes[1]);
        $returnCode = \proc_close($process);

        // Lines without trailing whitespace, as exec() returns them
        $stdout = \rtrim($stdout);
        $output = $stdout === '' ? [] : \array_map(\rtrim(...), \preg_split('/\r?\n/', $stdout) ?: []);

        // If command failed, throw exception
        if ($returnCode !== 0) {
            throw new BinaryExecutionException(
                \sprintf(
                    'Failed to execute binary "%s" with arguments %s. Exit code: %d. Output: %s',
                    $binaryPath,
                    $command,
                    $returnCode,
                    \implode("\n", $output),
                ),
            );
        }

        // Return combined output
        return $output;
    }
}
