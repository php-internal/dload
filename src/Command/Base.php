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
 * @internal
 */
abstract class Base extends Command
{
    protected Logger $logger;

    protected Container $container;

    public function configure(): void
    {
        parent::configure();
        $this->addOption('config', null, InputOption::VALUE_OPTIONAL, 'Path to the configuration file');
    }

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
