<?php

declare(strict_types=1);

namespace Internal\DLoad\Command;

use Internal\DLoad\Bootstrap;
use Internal\DLoad\Service\Container;
use Internal\DLoad\Service\Logger;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\StyleInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Base abstract class for all DLoad commands.
 * 
 * Provides common functionality for command initialization, container setup,
 * and configuration handling.
 * 
 * ```php
 * // Extend to create a custom command
 * final class CustomCommand extends Base
 * {
 *     protected function execute(InputInterface $input, OutputInterface $output): int
 *     {
 *         parent::execute($input, $output);
 *         // Command implementation
 *         return Command::SUCCESS;
 *     }
 * }
 * ```
 * 
 * @internal
 */
abstract class Base extends Command
{
    /** @var Logger Service for logging command execution */
    protected Logger $logger;

    /** @var Container IoC container with services */
    protected Container $container;

    /**
     * Configures command options.
     * 
     * Adds option for specifying configuration file location.
     */
    public function configure(): void
    {
        parent::configure();
        $this->addOption('config', null, InputOption::VALUE_OPTIONAL, 'Path to the configuration file');
    }

    /**
     * Initializes the command execution environment.
     * 
     * Sets up logger, container, and registers input/output in the container.
     * 
     * @param InputInterface $input Command input
     * @param OutputInterface $output Command output
     * 
     * @return int Command success code
     */
    protected function execute(
        InputInterface $input,
        OutputInterface $output,
    ): int {
        $this->logger = new Logger($output);
        $this->container = $container = Bootstrap::init()->withConfig(
            xml: $this->getConfigFile($input),
            inputOptions: $input->getOptions(),
            inputArguments: $input->getArguments(),
            environment: \getenv(),
        )->finish();

        $container->set($input, InputInterface::class);
        $container->set($output, OutputInterface::class);
        $container->set(new SymfonyStyle($input, $output), StyleInterface::class);
        $container->set($this->logger);

        return Command::SUCCESS;
    }

    /**
     * Resolves configuration file path from input or default location.
     * 
     * @param InputInterface $input Command input
     * 
     * @return non-empty-string|null Path to the configuration file
     */
    private function getConfigFile(InputInterface $input): ?string
    {
        /** @var string|null $config */
        $config = $input->getOption('config');
        $isConfigured = $config !== null;
        $config ??= './dload.xml';

        if (\is_file($config)) {
            return $config;
        }

        $isConfigured and throw new \InvalidArgumentException(
            'Configuration file not found: ' . $config,
        );

        return null;
    }
}