<?php

declare(strict_types=1);

namespace Internal\DLoad\Command;

use Internal\DLoad\Bootstrap;
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
        $container->set(new SymfonyStyle($input, $output), StyleInterface::class);
        $container->set($this->logger);

        $output->writeln('Architecture: ' . $container->get(Architecture::class)->name);
        $output->writeln('  Op. system: ' . $container->get(OperatingSystem::class)->name);
        $output->writeln('   Stability: ' . $container->get(Stability::class)->name);

        /** @var SoftwareCollection $softwareCollection */
        $softwareCollection = $container->get(SoftwareCollection::class);
        /** @var ArchiveFactory $archiveFactory */
        $archiveFactory = $container->get(ArchiveFactory::class);
        /** @var Downloader $downloader */
        $downloader = $container->get(Downloader::class);

        // / /*
        $task = $downloader->download(
            $softwareCollection->findSoftware('rr') ?? throw new \RuntimeException('Software not found.'),
            static fn() => null,
        );
        /*/
        $task = new \Internal\DLoad\Module\Downloader\Task\DownloadTask(
            $softwareCollection->findSoftware('rr') ?? throw new \RuntimeException('Software not found.'),
            static fn() => null,
            fn(): \React\Promise\PromiseInterface => \React\Promise\resolve(new DownloadResult(
                new \SplFileInfo('C:\Users\test\AppData\Local\Temp\roadrunner-2024.1.5-windows-amd64.zip'),
                '2024.1.5'
            )),
        );
        //*/

        ($task->handler)()->then(
            function (DownloadResult $downloadResult) use ($task, $archiveFactory, $output): void {
                $fileInfo = $downloadResult->file;
                $archive = $archiveFactory->create($fileInfo);
                $extractor = $archive->extract();

                while ($extractor->valid()) {
                    $file = $extractor->current();
                    \assert($file instanceof \SplFileInfo);

                    $to = $this->shouldBeExtracted($file, $task->software->files);

                    if ($to === null || !$this->checkExisting($to)) {
                        $extractor->next();
                        continue;
                    }

                    $extractor->send($to);

                    // Success
                    $path = $to->getRealPath() ?: $to->getPathname();
                    $output->writeln(\sprintf(
                        '%s (<comment>%s</comment>) has been installed into <info>%s</info>',
                        $to->getFilename(),
                        $downloadResult->version,
                        $path,
                    ));

                    $to->isExecutable() or @\chmod($path, 0755);
                }
            },
        );

        return Command::SUCCESS;
    }

    /**
     * @return bool True if the file should be extracted, false otherwise.
     */
    private function checkExisting(\SplFileInfo $bin): bool
    {
        if (! \is_file($bin->getPathname())) {
            return true;
        }

        /** @var StyleInterface $io */
        $io = $this->container->get(StyleInterface::class);
        $io->warning('File already exists: ' . $bin->getPathname());
        if (!$io->confirm('Do you want overwrite it?', false)) {
            $io->note('Skipping ' . $bin->getFilename() . ' installation...');
            return false;
        }

        return true;
    }

    /**
     * @param array<File> $mapping
     */
    private function shouldBeExtracted(\SplFileInfo $source, array $mapping): ?\SplFileInfo
    {
        /** @var Destination $destination */
        $destination = $this->container->get(Destination::class);
        $path = $destination->path ?? \getcwd();

        foreach ($mapping as $conf) {
            if (\preg_match($conf->pattern, $source->getFilename())) {
                $newName = match(true) {
                    $conf->rename === null => $source->getFilename(),
                    $source->getExtension() === '' => $conf->rename,
                    default => $conf->rename . '.' . $source->getExtension(),
                };

                return new \SplFileInfo($path . DIRECTORY_SEPARATOR . $newName);
            }
        }

        return null;
    }
}
