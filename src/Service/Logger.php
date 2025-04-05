<?php

declare(strict_types=1);

namespace Internal\DLoad\Service;

use Symfony\Component\Console\Output\OutputInterface;

/**
 * Console color logger for terminal output.
 *
 * Provides formatted console output with color support and verbosity control.
 *
 * ```php
 * $logger = new Logger($output);
 * $logger->info("Processing %s files", $count);
 * $logger->error("Failed to download %s", $url);
 * ```
 *
 * @internal
 */
final class Logger
{
    /** @var bool Whether debug messages should be displayed */
    private readonly bool $debug;

    /** @var bool Whether verbose messages should be displayed */
    private readonly bool $verbose;

    /**
     * @param OutputInterface|null $output Console output interface
     */
    public function __construct(
        private readonly ?OutputInterface $output = null,
    ) {
        $this->debug = $output?->isVeryVerbose() ?? false;
        $this->verbose = $output?->isVerbose() ?? false;
    }

    /**
     * Outputs a message with a newline.
     *
     * @param string $message Text to output
     */
    public function print(string $message): void
    {
        $this->echo($message . "\n", false);
    }

    /**
     * Displays a highlighted status message with sender identification.
     *
     * @param non-empty-string $sender Source component name
     * @param non-empty-string $message Message format string
     * @param string|int|float|bool ...$values Values to format the message
     */
    public function status(string $sender, string $message, string|int|float|bool ...$values): void
    {
        $this->echo("\033[47;1;30m " . $sender . " \033[0m " . \sprintf($message, ...self::values($values)) . "\n\n", false);
    }

    /**
     * Outputs a success/confirmation message in green.
     *
     * @param string $message Message format string
     * @param string|int|float|bool ...$values Format values
     */
    public function info(string $message, string|int|float|bool ...$values): void
    {
        $this->echo("\033[32m" . \sprintf($message, ...self::values($values)) . "\033[0m\n", false);
    }

    /**
     * Outputs a debug message in blue (only when debug mode is enabled).
     *
     * @param string $message Message format string
     * @param string|int|float|bool ...$values Format values
     */
    public function debug(string $message, string|int|float|bool ...$values): void
    {
        $this->echo("\033[34m" . \sprintf($message, ...self::values($values)) . "\033[0m\n");
    }

    /**
     * Outputs an error message in red.
     *
     * @param string $message Message format string
     * @param string|int|float|bool ...$values Format values
     */
    public function error(string $message, string|int|float|bool ...$values): void
    {
        $this->echo("\033[31m" . \sprintf($message, ...self::values($values)) . "\033[0m\n");
    }

    /**
     * Formats and outputs exception details.
     *
     * @param \Throwable $e Exception to display
     * @param string|null $header Optional header text
     * @param bool $important Whether to show regardless of debug setting
     */
    public function exception(\Throwable $e, ?string $header = null, bool $important = false): void
    {
        $r = "----------------------\n";
        // Print bold yellow header if exists
        if ($header !== null) {
            $r .= "\033[1;33m" . $header . "\033[0m\n";
        }
        // Print exception message
        $r .= $e->getMessage() . "\n";
        // Print file and line using green color and italic font
        $r .= "In \033[3;32m" . $e->getFile() . ':' . $e->getLine() . "\033[0m\n";
        // Print stack trace using gray
        $r .= "Stack trace:\n";
        // Limit stacktrace to 5 lines
        $stack = \explode("\n", $e->getTraceAsString());
        $r .= "\033[90m" . \implode("\n", \array_slice($stack, 0, \min(5, \count($stack)))) . "\033[0m\n";
        $r .= "\n";
        $this->echo($r, !$important);
    }

    /**
     * Converts values to string representations.
     *
     * @param array<\Stringable|string|int|float|bool> $values Values to convert
     * @return array<string> Converted values
     */
    private static function values(array $values): array
    {
        $result = [];
        foreach ($values as $k => $value) {
            $result[$k] = match (true) {
                \is_bool($value) => $value ? 'TRUE' : 'FALSE',
                default => (string) $value,
            };
        }

        return $result;
    }

    /**
     * Internal method to output text based on verbosity settings.
     *
     * @param string $message Text to output
     * @param bool $debug Whether message should be shown only in debug mode
     */
    private function echo(string $message, bool $debug = true): void
    {
        if ($debug && !$this->debug) {
            return;
        }
        $this->output?->write($message);
    }
}
