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
 * @internal
 */
#[AsCommand(
    name: 'software',
    description: 'List available software',
)]
final class ListSoftware extends Base
{
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

            foreach ($software->repositories as $repo) {
                $output->writeln("<fg=blue>{$repo->type}: {$repo->uri}</>");
            }

            $software->description and $output->writeln("<fg=gray>" . \wordwrap($software->description, 78) . "</>");
            $output->writeln('');
        }

        return Command::SUCCESS;
    }
}
