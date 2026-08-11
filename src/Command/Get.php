<?php

declare(strict_types=1);

namespace Internal\DLoad\Command;

use Internal\DLoad\DLoad;
use Internal\DLoad\Module\Common\Architecture;
use Internal\DLoad\Module\Common\OperatingSystem;
use Internal\DLoad\Module\Common\Stability;
use Internal\DLoad\Module\Config\Schema\Action\Download as DownloadConfig;
use Internal\DLoad\Module\Config\Schema\Actions;
use Internal\DLoad\Module\Downloader\Exception\DownloadFailed;
use Internal\DLoad\Service\Container;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\InvalidArgumentException;
use Symfony\Component\Console\Formatter\OutputFormatter;
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
 * # Download multiple software packages with versions and stability
 * ./vendor/bin/dload get rr dolt:1.44.1 temporal:1.*@alpha
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
        $this->addOption('stability', null, InputOption::VALUE_OPTIONAL, 'Minimum stability, e.g. "rc", "beta" etc.');
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
     * @return int `Command::SUCCESS` when every requested package is in place,
     *         `Command::FAILURE` when at least one download failed
     *
     * @throws \RuntimeException When no software is specified to download
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        parent::execute($input, $output);
        $container = $this->container;

        self::applyFlags($input, $container);

        /** @var Actions $actionsConfig */
        $actionsConfig = $container->get(Actions::class);
        $actions = self::getDownloadActions($input, $actionsConfig);

        $output->writeln('Architecture: ' . $container->get(Architecture::class)->name);
        $output->writeln('  Op. system: ' . $container->get(OperatingSystem::class)->name);
        $output->writeln('   Stability: ' . $container->get(Stability::class)->name);

        $actions === [] and throw new \RuntimeException('No software to download.');

        /** @var DLoad $dload */
        $dload = $container->get(DLoad::class);
        $forceDownload = (bool) $input->getOption('force');

        /** @var list<array{non-empty-string, \Throwable}> $failures */
        $failures = [];
        foreach ($actions as $action) {
            try {
                $dload->addTask($action, $forceDownload)->then(
                    null,
                    static function (\Throwable $e) use (&$failures, $action): void {
                        $failures[] = [$action->software, $e];
                    },
                );
            } catch (\Throwable $e) {
                // A task may fail before it is even scheduled, e.g. when the software is unknown
                $failures[] = [$action->software, $e];
            }
        }
        $dload->run();

        return $failures === []
            ? Command::SUCCESS
            : $this->reportFailures($output, $failures, \count($actions));
    }

    /**
     * Builds a user-facing explanation of a failure.
     */
    private static function describeFailure(\Throwable $error): string
    {
        $message = $error->getMessage();

        return match (true) {
            // The report already describes the whole context of the failure
            $error instanceof DownloadFailed => $error->report,
            $message === '' => \sprintf('Unexpected %s without a message.', $error::class),
            default => $message,
        };
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
    private static function getDownloadActions(InputInterface $input, Actions $actionsConfig): array
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

        $destinationPath = $input->getOption('path');

        return \array_map(
            static fn(string $software): DownloadConfig => $toDownload[$software] ?? self::parseSoftware(
                $software,
                $destinationPath,
            ),
            (array) $input->getArgument(self::ARG_SOFTWARE),
        );
    }

    /**
     * Parses a software identifier into a download configuration.
     *
     * Supports "name:version" format to specify exact versions.
     * E.g. "rr:2.10.0", "dolt:1.2.3@beta", "temporal:1.3.1-priority", etc.
     *
     * @param non-empty-string $software Software identifier, e.g. "rr" or "dolt:1.2.3"
     * @param non-empty-string|null $destinationPath Optional path to store the binary
     * @return DownloadConfig Parsed download configuration
     */
    private static function parseSoftware(string $software, ?string $destinationPath): DownloadConfig
    {
        [$name, $version] = \explode(':', $software, 2) + [1 => ''];
        $name === '' and throw new InvalidArgumentException("Software name cannot be empty, given: {$software}.");

        $action = DownloadConfig::fromSoftwareId($name);
        $version === '' or $action->version = $version;
        $action->extractPath = $destinationPath;

        return $action;
    }

    /**
     * Applies command-line flags to override container settings.
     *
     * Sets architecture, operating system, and stability in the container
     * based on provided CLI options.
     *
     * @param InputInterface $input Command input
     * @param Container $container Dependency injection container
     *
     * @throws InvalidArgumentException When an unknown value is provided
     */
    private static function applyFlags(InputInterface $input, Container $container): void
    {
        $stability = (string) $input->getOption('stability');
        $stability === '' or $container->set(
            Stability::fromString((string) $input->getOption('stability')) ?? throw new InvalidArgumentException(
                "Unknown stability level: {$stability}.",
            ),
        );

        $os = (string) $input->getOption('os');
        $os === '' or $container->set(OperatingSystem::tryFromString($os) ?? throw new InvalidArgumentException(
            "Unknown operating system: {$os}.",
        ));

        $arch = (string) $input->getOption('arch');
        $arch === '' or $container->set(Architecture::tryFromString($arch) ?? throw new InvalidArgumentException(
            "Unknown architecture: {$arch}.",
        ));
    }

    /**
     * Prints the reason of every failed download and returns a failure exit code.
     *
     * @param list<array{non-empty-string, \Throwable}> $failures Software identifier with its failure
     * @param int<1, max> $total Total number of requested downloads
     */
    private function reportFailures(OutputInterface $output, array $failures, int $total): int
    {
        foreach ($failures as [$software, $error]) {
            $output->writeln('');
            $output->writeln(\sprintf('<error> Failed to download `%s` </error>', $software));
            $output->writeln(OutputFormatter::escape(self::describeFailure($error)));

            $output->isVeryVerbose() and $this->logger->exception($error, important: true);
        }

        $output->writeln('');
        $output->writeln(
            \sprintf('<comment>%d of %d download(s) failed.</comment>', \count($failures), $total),
        );

        return Command::FAILURE;
    }
}
