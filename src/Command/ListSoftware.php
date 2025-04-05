<?php

declare(strict_types=1);

namespace Internal\DLoad\Command;

use Internal\DLoad\Module\Common\Config\Embed\Software;
use Internal\DLoad\Module\Downloader\SoftwareCollection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Displays a list of available software packages.
 *
 * Shows all registered software packages with their IDs, names,
 * repository information, and descriptions.
 *
 * ```php
 * // List software programmatically
 * $command = new ListSoftware();
 * $command->run(new ArrayInput([]), new ConsoleOutput());
 * ```
 *
 * ```bash
 * # List all available software packages
 * ./vendor/bin/dload software
 *
 * # List software with custom configuration
 * ./vendor/bin/dload software --config=./custom-dload.xml
 * ```
 *
 * @internal
 */
#[AsCommand(
    name: 'software',
    description: 'List available software',
)]
final class ListSoftware extends Base
{
    /**
     * Lists all available software packages in a formatted output.
     *
     * Displays the software ID, name, homepage (if available),
     * repository information, and description for each registered software.
     *
     * @param InputInterface $input Command input
     * @param OutputInterface $output Command output
     *
     * @return int Command result code
     */
    protected function execute(
        InputInterface $input,
        OutputInterface $output,
    ): int {
        parent::execute($input, $output);

        /** @var SoftwareCollection $registry */
        $registry = $this->container->get(SoftwareCollection::class);

        $output->writeln('There are <options=bold>' . $registry->count() . '</> software available:');
        $output->writeln('');

        /** @var Software $software */
        foreach ($registry->getIterator() as $software) {
            $output->writeln("<fg=green;options=bold>{$software->getId()}</>  $software->name");
            $software->homepage and $output->writeln("<fg=blue>Homepage: $software->homepage</>");

            foreach ($software->repositories as $repo) {
                $output->writeln("<fg=blue>{$repo->type}: {$repo->uri}</>");
            }

            $software->description and $output->writeln("<fg=gray>" . \wordwrap($software->description, 78) . "</>");
            $output->writeln('');
        }

        return Command::SUCCESS;
    }
}
