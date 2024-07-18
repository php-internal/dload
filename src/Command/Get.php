<?php

declare(strict_types=1);

namespace Internal\DLoad\Command;

use Internal\DLoad\Bootstrap;
use Internal\DLoad\DLoad;
use Internal\DLoad\Module\Archive\ArchiveFactory;
use Internal\DLoad\Module\Common\Architecture;
use Internal\DLoad\Module\Common\Config\Destination;
use Internal\DLoad\Module\Common\Config\Embed\File;
use Internal\DLoad\Module\Common\OperatingSystem;
use Internal\DLoad\Module\Common\Stability;
use Internal\DLoad\Module\Downloader\Downloader;
use Internal\DLoad\Module\Downloader\SoftwareCollection;
use Internal\DLoad\Module\Downloader\Task\DownloadResult;
use Internal\DLoad\Service\Container;
use Internal\DLoad\Service\Logger;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Command\SignalableCommandInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\StyleInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * @internal
 */
#[AsCommand(
    name: 'get',
    description: 'Download a binary',
)]
final class Get extends Command implements SignalableCommandInterface
{
    private bool $cancelling = false;

    private Logger $logger;

    private Container $container;

    public function configure(): void
    {
        $this->addArgument('binary', InputArgument::REQUIRED, 'Binary name, e.g. "rr", "dolt", "temporal" etc.');
        $this->addOption('path', null, InputOption::VALUE_OPTIONAL, 'Path to store the binary, e.g. "./bin"', ".");
        $this->addOption('rename', null, InputOption::VALUE_OPTIONAL, 'Rename the binary, e.g. "rr"');
        $this->addOption('arch', null, InputOption::VALUE_OPTIONAL, 'Architecture, e.g. "amd64", "arm64" etc.');
        $this->addOption('os', null, InputOption::VALUE_OPTIONAL, 'Operating system, e.g. "linux", "darwin" etc.');
        $this->addOption('stability', null, InputOption::VALUE_OPTIONAL, 'Stability, e.g. "stable", "beta" etc.');
    }

    public function handleSignal(int $signal, int|false $previousExitCode = 0): int|false
    {
        if (\defined('SIGINT') && $signal === \SIGINT) {
            $this->cancelling = true;
        }

        if (\defined('SIGTERM') && $signal === \SIGTERM) {
            return $signal;
        }

        return false;
    }

    public function getSubscribedSignals(): array
    {
        $result = [];
        /** @psalm-suppress MixedAssignment */
        \defined('SIGINT') and $result[] = \SIGINT;
        /** @psalm-suppress MixedAssignment */
        \defined('SIGTERM') and $result[] = \SIGTERM;

        return $result;
    }

    protected function execute(
        InputInterface $input,
        OutputInterface $output,
    ): int {
        $this->logger = new Logger($output);
        $output->writeln('Binary to load: ' . $input->getArgument('binary'));
        $output->writeln('Path to store the binary: ' . $input->getOption('path'));

        $this->container = $container = Bootstrap::init()->withConfig(
            xml: \dirname(__DIR__, 2) . '/dload.xml',
            inputOptions: $input->getOptions(),
            inputArguments: $input->getArguments(),
            environment: \getenv(),
        )->finish();
        $container->set($input, InputInterface::class);
        $container->set($output, OutputInterface::class);
        $container->set(new SymfonyStyle($input, $output), StyleInterface::class);
        $container->set($this->logger);

        $output->writeln('Architecture: ' . $container->get(Architecture::class)->name);
        $output->writeln('  Op. system: ' . $container->get(OperatingSystem::class)->name);
        $output->writeln('   Stability: ' . $container->get(Stability::class)->name);

        /** @var DLoad $dload */
        $dload = $container->get(DLoad::class);

        $binary = $input->getArgument('binary');
        $dload->addTask($binary);
        $dload->run();

        return Command::SUCCESS;
    }
}
