<?php

declare(strict_types=1);

namespace Internal\DLoad\Command;

use Internal\DLoad\Module\Binary\Binary;
use Internal\DLoad\Module\Binary\BinaryProvider;
use Internal\DLoad\Module\Common\Config\Actions;
use Internal\DLoad\Module\Downloader\SoftwareCollection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'show',
    description: 'Shows information about downloaded software',
)]
final class Show extends Base
{
    public function configure(): void
    {
        parent::configure();
        $this->addArgument(
            'software',
            InputArgument::OPTIONAL,
            'Software name to show detailed information about',
        );
    }

    protected function execute(
        InputInterface $input,
        OutputInterface $output,
    ): int {
        // Always call parent execute first to initialize services
        parent::execute($input, $output);

        // Get all software from collection
        $collection = $this->container->get(SoftwareCollection::class);
        $binaryProvider = $this->container->get(BinaryProvider::class);
        $softwareName = $input->getArgument('software');

        // Get configuration if available
        $configFile = $this->getConfigFile($input);
        $actions = null;

        if ($configFile !== null) {
            $actions = $this->container->get(Actions::class);
        }

        if ($softwareName !== null) {
            return $this->showSoftwareDetails($softwareName, $collection, $binaryProvider, $actions, $output);
        }

        return $this->listAllSoftware($collection, $binaryProvider, $actions, $output);
    }

    private function listAllSoftware(
        SoftwareCollection $collection,
        BinaryProvider $binaryProvider,
        ?Actions $actions,
        OutputInterface $output,
    ): int {
        $output->writeln('<info>Downloaded software:</info>');

        $configSoftwareIds = [];
        if ($actions !== null) {
            $configSoftwareIds = \array_map(
                static fn($download) => $download->software,
                $actions->downloads,
            );
        }

        $foundAny = false;
        foreach ($collection as $software) {
            // Skip software without binaries
            if ($software->binary === null) {
                continue;
            }

            $destinationPath = \getcwd();

            // Get binary instance if available
            $binary = $binaryProvider->getBinary($destinationPath, $software->binary);
            if ($binary === null) {
                continue;
            }

            $foundAny = true;
            $inConfig = \in_array($software->getId(), $configSoftwareIds, true);

            // Format the output line
            $output->writeln(\sprintf(
                '  <info>%s</info> (%s) %s%s',
                $software->getId(),
                $binary->getVersion() ?? 'unknown',
                $binary->getPath(),
                $inConfig ? ' <comment>[from config]</comment>' : '',
            ));
        }

        if (!$foundAny) {
            $output->writeln('  <comment>No downloaded software found</comment>');
        }

        return Command::SUCCESS;
    }

    private function showSoftwareDetails(
        string $softwareName,
        SoftwareCollection $collection,
        BinaryProvider $binaryProvider,
        ?Actions $actions,
        OutputInterface $output,
    ): int {
        $software = $collection->findSoftware($softwareName);

        if ($software === null) {
            $output->writeln(\sprintf('<error>Software "%s" not found in registry</error>', $softwareName));
            return Command::FAILURE;
        }

        if ($software->binary === null) {
            $output->writeln(\sprintf('<error>Software "%s" does not have a binary</error>', $softwareName));
            return Command::FAILURE;
        }

        $destinationPath = \getcwd();


        // Check if software is in project config
        $inConfig = false;
        $configConstraints = null;
        $configExtractPath = null;

        if ($actions !== null) {
            foreach ($actions->downloads as $download) {
                if ($download->software === $software->getId()) {
                    $inConfig = true;
                    $configConstraints = $download->version;
                    $configExtractPath = $download->extractPath;
                    break;
                }
            }
        }

        // Display detailed information
        $output->writeln(\sprintf('<info>Software:</info> %s', $software->name));

        $software->alias === null or $software->alias === $software->name or $output
            ->writeln(\sprintf('<info>Alias:</info> %s', $software->alias));
        $software->description and $output
            ->writeln(\sprintf('<info>Description:</info> %s', $software->description));
        $software->homepage and $output
            ->writeln(\sprintf('<info>Homepage:</info> %s', $software->homepage));

        // Show project config information
        $output->writeln('');
        if ($actions !== null) {
            if ($inConfig) {
                $output->writeln('<info>Project configuration:</info> <comment>Included in project config</comment>');

                if ($configConstraints !== null) {
                    $output->writeln(\sprintf(
                        '  <info>Version constraint:</info> %s',
                        $configConstraints,
                    ));
                }

                if ($configExtractPath !== null) {
                    $output->writeln(\sprintf(
                        '  <info>Extract path:</info> %s',
                        $configExtractPath,
                    ));
                }
            } else {
                $output->writeln('<info>Project configuration:</info> <comment>Not included in project config</comment>');
            }
        }

        // Binary information
        $binary = $binaryProvider->getBinary($destinationPath, $software->binary);

        $this->displayBinaryDetails($binary, $output);

        return Command::SUCCESS;
    }

    private function displayBinaryDetails(?Binary $binary, OutputInterface $output): void
    {
        $output->writeln('');
        if ($binary === null) {
            $output->writeln('<error>Binary not exists</error>');
            return;
        }

        $binaryPath = $binary->getPath();

        $output->writeln('<info>Binary information:</info>');
        $output->writeln(\sprintf('  <info>Full path:</info> %s', $binaryPath->absolute()));
        $output->writeln(\sprintf('  <info>Version:</info> %s', $binary->getVersion() ?? 'unknown'));
        $output->writeln(\sprintf('  <info>Size:</info> %s', $this->formatSize($binary->getSize())));

        $output->writeln(\sprintf(
            '  <info>Last modified:</info> %s',
            $binary->getMTime()->format('Y-m-d H:i:s'),
        ));
    }

    private function formatSize(?int $bytes): string
    {
        if ($bytes === null) {
            return 'unknown';
        }

        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = 0;
        while ($bytes >= 1024 && $i < \count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }

        return \sprintf('%.2f %s', $bytes, $units[$i]);
    }
}
