<?php

declare(strict_types=1);

namespace Internal\DLoad\Command;

use Internal\DLoad\Module\Registry\Record\RepositoryRecord;
use Internal\DLoad\Module\Registry\RegistryStorage;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Formatter\OutputFormatter;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;

/**
 * Removes stored release listings from the version registry.
 *
 * Without arguments the whole registry is dropped. With software identifiers only the
 * repositories those packages are served from are forgotten, so the next run fetches their
 * releases anew.
 *
 * ```bash
 * # Forget every stored release listing
 * ./vendor/bin/dload cache:clear
 *
 * # Forget the repositories RoadRunner and Temporal are served from
 * ./vendor/bin/dload cache:clear rr temporal
 * ```
 *
 * @internal
 */
#[AsCommand(
    name: 'cache:clear',
    description: 'Remove stored release listings from the version registry',
)]
final class CacheClear extends Base
{
    private const ARG_SOFTWARE = 'software';
    private const OPTION_FORCE = 'force';

    public function configure(): void
    {
        parent::configure();
        $this->addArgument(
            self::ARG_SOFTWARE,
            InputArgument::OPTIONAL | InputArgument::IS_ARRAY,
            'Software whose repositories must be forgotten, e.g. "rr", "dolt". Everything when omitted.',
        );
        $this->addOption(
            self::OPTION_FORCE,
            'f',
            InputOption::VALUE_NONE,
            'Clear the whole registry without asking for confirmation',
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        parent::execute($input, $output);

        $storage = $this->container->get(RegistryStorage::class);

        /** @var list<non-empty-string> $software */
        $software = \array_values(\array_filter(
            (array) $input->getArgument(self::ARG_SOFTWARE),
            static fn(mixed $name): bool => \is_string($name) && $name !== '',
        ));

        if ($software === []) {
            if (!$this->confirmed($input, $output)) {
                $output->writeln('<comment>The version registry is left as it is.</comment>');

                return Command::SUCCESS;
            }

            $storage->clear();
            $output->writeln('<info>The version registry has been cleared.</info>');

            return Command::SUCCESS;
        }

        $removed = 0;
        foreach ($this->recordsOf($storage, $software) as $record) {
            $storage->remove($record->id);
            $output->writeln(\sprintf('Forgot <comment>%s</comment>', OutputFormatter::escape((string) $record->id)));
            ++$removed;
        }

        $output->writeln(\sprintf('<info>%d repository listing(s) removed.</info>', $removed));

        return Command::SUCCESS;
    }

    /**
     * Whether the whole registry may be dropped: a non-interactive run goes ahead, a person is asked.
     */
    private function confirmed(InputInterface $input, OutputInterface $output): bool
    {
        if ((bool) $input->getOption(self::OPTION_FORCE) || !$input->isInteractive()) {
            return true;
        }

        /** @var QuestionHelper $helper */
        $helper = $this->getHelper('question');

        return (bool) $helper->ask(
            $input,
            $output,
            new ConfirmationQuestion('Forget every stored release listing? [y/N] ', false),
        );
    }

    /**
     * @param list<non-empty-string> $software
     * @return \Generator<int, RepositoryRecord>
     */
    private function recordsOf(RegistryStorage $storage, array $software): \Generator
    {
        foreach ($storage->all() as $record) {
            \array_intersect($record->software, $software) === [] or yield $record;
        }
    }
}
