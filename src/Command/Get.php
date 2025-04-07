<?php

declare(strict_types=1);

namespace Internal\DLoad\Command;

use Internal\DLoad\DLoad;
use Internal\DLoad\Module\Common\Architecture;
use Internal\DLoad\Module\Common\Config\Action\Download as DownloadConfig;
use Internal\DLoad\Module\Common\Config\Actions;
use Internal\DLoad\Module\Common\OperatingSystem;
use Internal\DLoad\Module\Common\Stability;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Fetches software packages based on command arguments or configuration.
 *
 * Downloads software binaries from configured repositories with proper
 * system/architecture detection. Can work with both CLI arguments and
 * configuration file definitions.
 *
 * ```bash
 * # Download single software
 * ./vendor/bin/dload get rr
 *
 * # Download specific version of software
 * ./vendor/bin/dload get rr --stability=beta
 *
 * # Download multiple software packages
 * ./vendor/bin/dload get rr dolt temporal
 *
 * # Download software defined in config file
 * ./vendor/bin/dload get --config=./dload.xml
 *
 * # Force download even if binary exists
 * ./vendor/bin/dload get rr --force
 * ```
 *
 * @internal
 */
#[AsCommand(
    name: 'get',
    description: 'Download a binary',
)]
final class Get extends Base
{
    /** @var string Argument name for software identifiers */
    private const ARG_SOFTWARE = 'software';

    /**
     * Configures command arguments and options.
     */
    public function configure(): void
    {
        parent::configure();
        $this->addArgument(
            self::ARG_SOFTWARE,
            InputArgument::OPTIONAL | InputArgument::IS_ARRAY,
            'Software name, e.g. "rr", "dolt", "temporal" etc.',
        );
        $this->addOption('path', null, InputOption::VALUE_OPTIONAL, 'Path to store the binary, e.g. "./bin"', ".");
        $this->addOption('arch', null, InputOption::VALUE_OPTIONAL, 'Architecture, e.g. "amd64", "arm64" etc.');
        $this->addOption('os', null, InputOption::VALUE_OPTIONAL, 'Operating system, e.g. "linux", "darwin" etc.');
        $this->addOption('stability', null, InputOption::VALUE_OPTIONAL, 'Stability, e.g. "stable", "beta" etc.');
        $this->addOption('force', 'f', InputOption::VALUE_NONE, 'Force download even if binary exists');
    }

    /**
     * Executes the command to download specified software.
     *
     * Determines system parameters, resolves download actions based on input,
     * and runs the download tasks.
     *
     * @param InputInterface $input Command input
     * @param OutputInterface $output Command output
     *
     * @return int Command result code
     *
     * @throws \RuntimeException When no software is specified to download
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        parent::execute($input, $output);
        $container = $this->container;

        /** @var Actions $actionsConfig */
        $actionsConfig = $container->get(Actions::class);
        $actions = $this->getDownloadActions($input, $actionsConfig);

        $output->writeln('Architecture: ' . $container->get(Architecture::class)->name);
        $output->writeln('  Op. system: ' . $container->get(OperatingSystem::class)->name);
        $output->writeln('   Stability: ' . $container->get(Stability::class)->name);

        $actions === [] and throw new \RuntimeException('No software to download.');

        /** @var DLoad $dload */
        $dload = $container->get(DLoad::class);
        $forceDownload = $input->getOption('force');

        foreach ($actions as $action) {
            $dload->addTask($action, $forceDownload);
        }
        $dload->run();

        return Command::SUCCESS;
    }

    /**
     * Resolves download actions from input arguments or config.
     *
     * Uses explicitly specified software names from CLI arguments if present,
     * otherwise falls back to configuration file definitions.
     *
     * @param InputInterface $input Command input
     * @param Actions $actionsConfig Available actions from config
     *
     * @return list<DownloadConfig> List of download configurations to process
     */
    private function getDownloadActions(InputInterface $input, Actions $actionsConfig): array
    {
        $argument = $input->getArgument(self::ARG_SOFTWARE);
        if ($argument === []) {
            // Use configured actions if CLI arguments are empty
            return $actionsConfig->downloads;
        }

        $toDownload = [];
        foreach ($actionsConfig->downloads as $action) {
            $toDownload[$action->software] = $action;
        }

        return \array_map(
            static fn(mixed $software): DownloadConfig => $toDownload[$software]
                ?? DownloadConfig::fromSoftwareId((string) $software),
            $input->getArgument(self::ARG_SOFTWARE),
        );
    }
}
