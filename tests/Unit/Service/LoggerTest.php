<?php

declare(strict_types=1);

namespace Internal\DLoad\Tests\Unit\Service;

use Internal\DLoad\Service\Logger;
use Symfony\Component\Console\Output\BufferedOutput;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Data\DataSet;
use Testo\Test;

#[Test]
#[Covers(Logger::class)]
final class LoggerTest
{
    #[DataSet(["\e[31mred\e[0m", '[31mred[0m'], 'a color sequence')]
    #[DataSet(["\e]8;;https://evil.example\e\\link\e]8;;\e\\", ']8;;https://evil.example\\link]8;;\\'], 'a hyperlink sequence')]
    #[DataSet(["a\rb", 'ab'], 'a carriage return')]
    #[DataSet(["a\x08b\x07c\x00d\x7Fe", 'abcde'], 'backspace, bell, null and delete')]
    #[DataSet(["line\nnext\tcell", "line\nnext\tcell"], 'a line feed and a tab')]
    #[DataSet(["a\u{9B}31mb", 'a31mb'], 'an 8-bit CSI')]
    #[DataSet(['привет', 'привет'], 'non-ASCII text')]
    #[DataSet(['plain text', 'plain text'], 'plain text')]
    public function controlCharactersOfValuesDoNotReachTheTerminal(string $value, string $printed): void
    {
        $output = new BufferedOutput();

        (new Logger($output))->warning('Value: %s.', $value);

        Assert::same($output->fetch(), "\033[33mValue: $printed.\033[0m\n");
    }

    #[DataSet(["\e[2Jtitle", '[2Jtitle'], 'an escape sequence')]
    #[DataSet(["\u{9B}2Jtitle", '2Jtitle'], 'a C1 CSI in UTF-8')]
    #[DataSet(["\u{9D}8;;https://evil.example\u{9C}link", '8;;https://evil.examplelink'], 'a C1 OSC and ST in UTF-8')]
    #[DataSet(["\u{85}next", 'next'], 'a C1 next line in UTF-8')]
    #[DataSet(["tab\tline\n", "tab\tline\n"], 'a tab and a line feed')]
    #[DataSet(["a\rb\x00c\x7Fd", 'abcd'], 'carriage return, null and delete')]
    #[DataSet(['Größe: 10 €, ¡olé!', 'Größe: 10 €, ¡olé!'], 'non-ASCII text')]
    #[DataSet(['привет 你好 ✓', 'привет 你好 ✓'], 'other scripts')]
    public function printableRemovesWhatATerminalActsOn(string $text, string $printable): void
    {
        Assert::same(Logger::printable($text), $printable);
    }

    public function anExceptionMessageIsPrintedWithoutControlCharacters(): void
    {
        $output = new BufferedOutput();

        (new Logger($output))->exception(new \RuntimeException("Asset \e]8;;https://evil.example\e\\x\u{9B}31m failed"), important: true);

        Assert::string($output->fetch())
            ->contains("Asset ]8;;https://evil.example\\x31m failed\n")
            ->notContains("\e]")
            ->notContains("\u{9B}");
    }

    #[DataSet(['<fg=black;bg=black>HIDDEN</>'], 'a hiding style')]
    #[DataSet(['<href=https://evil.example>click</>'], 'a hyperlink')]
    #[DataSet(['<error>alarm</error>'], 'a named style')]
    public function tagsInValuesArePrintedLiterally(string $value): void
    {
        $output = new BufferedOutput(BufferedOutput::VERBOSITY_VERY_VERBOSE, decorated: true);
        $logger = new Logger($output);

        $logger->warning('%s', $value);
        $logger->info('%s', $value);
        $logger->error('%s', $value);
        $logger->debug('%s', $value);
        $logger->status('sender', '%s', $value);

        $printed = $output->fetch();
        Assert::same(\substr_count($printed, $value), 5);
        Assert::string($printed)
            ->notContains("\e[30;40m")
            ->notContains("\e]8;;")
            ->notContains("\e[37;41malarm");
    }

    public function tagsInAnExceptionMessageArePrintedLiterally(): void
    {
        $output = new BufferedOutput(decorated: true);
        $message = 'failed: <fg=black;bg=black>HIDDEN</> <href=https://evil.example>click</>';

        (new Logger($output))->exception(new \RuntimeException($message), important: true);

        Assert::string($output->fetch())
            ->contains($message . "\n")
            ->notContains("\e[30;40m")
            ->notContains("\e]8;;");
    }

    /**
     * PHP keeps the first 15 characters of each string argument in the stack trace, which the
     * exception log prints: an argument is as foreign as the message.
     */
    #[DataSet(['<fg=red>red</>', "\e[31mred"], 'a style')]
    #[DataSet(['<fg=nope>x</>', 'Invalid "nope" color'], 'an invalid style')]
    public function tagsInStackTraceArgumentsArePrintedLiterally(string $argument, string $rendered): void
    {
        $output = new BufferedOutput(decorated: true);
        $exception = (static fn(string $id): \RuntimeException => new \RuntimeException('failed'))($argument);

        (new Logger($output))->exception($exception, important: true);

        Assert::string($output->fetch())
            ->contains("'$argument'")
            ->notContains($rendered);
    }

    #[DataSet(['C:\\dir\\'], 'a trailing backslash')]
    #[DataSet(['a\\b'], 'an inner backslash')]
    #[DataSet(['1 < 2 > 0'], 'comparison signs')]
    public function valuesWithoutTagsArePrintedAsIs(string $value): void
    {
        $output = new BufferedOutput(decorated: true);

        (new Logger($output))->warning('[%s]', $value);

        Assert::same($output->fetch(), "\e[33m[$value]\e[0m\n");
    }

    public function everyMessageKindCleansItsValues(): void
    {
        $output = new BufferedOutput(BufferedOutput::VERBOSITY_VERY_VERBOSE);
        $logger = new Logger($output);

        $logger->info('%s', "\e[2Jinfo");
        $logger->error('%s', "\e[2Jerror");
        $logger->debug('%s', "\e[2Jdebug");
        $logger->status('sender', '%s', "\e[2Jstatus");

        Assert::string($output->fetch())
            ->notContains("\e[2J")
            ->contains('[2Jinfo')
            ->contains('[2Jerror')
            ->contains('[2Jdebug')
            ->contains('[2Jstatus');
    }
}
