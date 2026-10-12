<?php

declare(strict_types=1);

namespace Internal\DLoad\Command;

use Internal\DLoad\Module\Software\Entry;
use Internal\DLoad\Module\Software\OriginKind;
use Internal\DLoad\Module\Software\SoftwareCollection;
use Internal\DLoad\Service\Logger;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Formatter\OutputFormatter;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Displays a list of available software packages.
 *
 * Shows all registered software packages with their IDs, names,
 * repository information, and descriptions.
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

        // Definitions may come from Composer packages: their text must turn neither into console tags
        // nor into terminal control sequences
        $e = static fn(string $text): string => OutputFormatter::escape(Logger::printable($text));

        foreach ($registry->entries() as $entry) {
            $software = $entry->software;
            $output->writeln(\sprintf(
                '<fg=green;options=bold>%s</>  %s  <fg=gray>[%s]</>',
                $e($software->getId()),
                $e($software->name),
                $e((string) $entry->origin),
            ));

            $shadowed = \array_map(
                static fn(Entry $other): string => $other->origin->kind === OriginKind::Package
                    ? "{$other->origin}#{$other->getId()}"
                    : (string) $other->origin,
                \array_filter(
                    $registry->candidates($software->getId()),
                    static fn(Entry $other): bool => $other !== $entry,
                ),
            );
            $shadowed === [] or $output->writeln('<fg=yellow>Shadows: ' . $e(\implode(', ', $shadowed)) . '</>');
            $software->homepage and $output->writeln('<fg=blue>Homepage: ' . $e($software->homepage) . '</>');

            foreach ($software->repositories as $repo) {
                $output->writeln('<fg=blue>' . $e("{$repo->type}: {$repo->uri}") . '</>');
            }

            $software->description and $output->writeln('<fg=gray>' . $e(\wordwrap($software->description, 78)) . '</>');
            $output->writeln('');
        }

        return Command::SUCCESS;
    }
}
