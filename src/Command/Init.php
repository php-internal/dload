<?php

declare(strict_types=1);

namespace Internal\DLoad\Command;

use Internal\DLoad\Module\Common\FileSystem\Path;
use Internal\DLoad\Module\Config\Schema\Action\Download as DownloadConfig;
use Internal\DLoad\Module\Config\Schema\Embed\Software;
use Internal\DLoad\Module\Config\ConfigBuilder;
use Internal\DLoad\Module\Downloader\SoftwareCollection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Style\StyleInterface;

/**
 * Creates initial DLoad configuration file with interactive prompts.
 *
 * Guides users through selecting software packages and generates a properly
 * formatted dload.xml configuration file with schema validation.
 *
 * ```bash
 * # Create initial configuration interactively
 * ./vendor/bin/dload init
 *
 * # Create configuration in specific location
 * ./vendor/bin/dload init --config=./custom-dload.xml
 *
 * # Create configuration without prompts (minimal setup)
 * ./vendor/bin/dload init --no-interaction
 * ```
 *
 * @internal
 */
#[AsCommand(
    name: 'init',
    description: 'Create initial DLoad configuration file',
)]
final class Init extends Base
{
    private const DEFAULT_CONFIG_PATH = './dload.xml';

    /**
     * Configures command options.
     */
    public function configure(): void
    {
        parent::configure();
        $this->addOption(
            'overwrite',
            null,
            InputOption::VALUE_NONE,
            'Overwrite existing configuration file without confirmation',
        );
    }

    /**
     * Executes the configuration initialization.
     *
     * Creates an interactive session to select software packages and generates
     * a configuration file with proper XML structure and schema reference.
     *
     * @param InputInterface $input Command input
     * @param OutputInterface $output Command output
     *
     * @return int Command result code
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        parent::execute($input, $output);

        /** @var StyleInterface $style */
        $style = $this->container->get(StyleInterface::class);

        $configPath = $this->getTargetConfigPath($input);

        if ($this->shouldAbortDueToExistingFile($input, $style, $configPath)) {
            return Command::FAILURE;
        }

        $style->title('DLoad Configuration Initialization');
        $style->text('This command will help you create a dload.xml configuration file.');

        if ($input->isInteractive()) {
            $downloadActions = $this->collectDownloadActions($input, $output, $style);
        } else {
            $downloadActions = [];
            $style->text('Creating minimal configuration without download actions.');
        }

        $this->generateConfigFile($configPath, $downloadActions);

        $style->success("Configuration file created: {$configPath}");

        if ($downloadActions === []) {
            $style->text('You can add download actions by editing the file or running this command again.');
        } else {
            $style->text('Run "dload get" to download the configured software.');
        }

        return Command::SUCCESS;
    }

    /**
     * Determines the target configuration file path.
     *
     * @param InputInterface $input Command input
     * @return Path Configuration file path
     */
    private function getTargetConfigPath(InputInterface $input): Path
    {
        /** @var string|null $configOption */
        $configOption = $input->getOption('config');

        return Path::create($configOption ?? self::DEFAULT_CONFIG_PATH);
    }

    /**
     * Checks if initialization should be aborted due to existing configuration file.
     *
     * @param InputInterface $input Command input
     * @param StyleInterface $style Console style helper
     * @param Path $configPath Target configuration path
     *
     * @return bool True if should abort, false to continue
     */
    private function shouldAbortDueToExistingFile(
        InputInterface $input,
        StyleInterface $style,
        Path $configPath,
    ): bool {
        if (!$configPath->exists() || $input->getOption('overwrite')) {
            return false;
        }

        if (!$input->isInteractive()) {
            $style->error("Configuration file already exists: {$configPath}");
            $style->text('Use --overwrite to replace it or specify a different path with --config.');
            return true;
        }

        $question = new ConfirmationQuestion(
            "Configuration file already exists at {$configPath}. Overwrite it? [y/N] ",
            false,
        );

        /** @var QuestionHelper $helper */
        $helper = $this->getHelper('question');

        return !$helper->ask($input, $style, $question);
    }

    /**
     * Collects download actions through interactive prompts.
     *
     * @param InputInterface $input Command input
     * @param OutputInterface $output Command output
     * @param StyleInterface $style Console style helper
     *
     * @return list<DownloadConfig> Download actions to include in configuration
     */
    private function collectDownloadActions(
        InputInterface $input,
        OutputInterface $output,
        StyleInterface $style,
    ): array {
        /** @var SoftwareCollection $softwareCollection */
        $softwareCollection = $this->container->get(SoftwareCollection::class);

        $availableSoftware = $this->getAvailableSoftwareChoices($softwareCollection);
        $helper = $this->getHelper('question');

        $style->section('Software Selection');
        $style->text('Add software packages to your configuration one by one.');
        $style->text('Press Enter without typing anything to finish adding software.');
        $style->newLine();

        $actions = [];
        $iteration = 1;

        while (true) {
            $question = new Question("Software #{$iteration} (or Enter to finish): ");
            $question->setAutocompleterValues(\array_keys($availableSoftware));

            $softwareName = $helper->ask($input, $output, $question);

            // Empty input means we're done
            if ($softwareName === null || \trim($softwareName) === '') {
                break;
            }

            $softwareName = \trim($softwareName);
            $action = $this->processSoftwareEntry($softwareName, $softwareCollection, $style);

            if ($action !== null) {
                $actions[] = $action;
                $iteration++;
            }

            $style->newLine();
        }

        if ($actions === []) {
            $style->text('No software packages were added to the configuration.');

            $question = new ConfirmationQuestion(
                'Would you like to see available software packages? [y/N] ',
                false,
            );

            if ($helper->ask($input, $output, $question)) {
                return $this->showSoftwareMenu($input, $output, $style, $availableSoftware);
            }
        }

        return $actions;
    }

    /**
     * Processes a single software entry with immediate validation and feedback.
     *
     * @param non-empty-string $softwareName Software name entered by user
     * @param SoftwareCollection $collection Software collection for validation
     * @param StyleInterface $style Console style helper
     *
     * @return DownloadConfig|null Download action if valid, null if should retry
     */
    private function processSoftwareEntry(
        string $softwareName,
        SoftwareCollection $collection,
        StyleInterface $style,
    ): ?DownloadConfig {
        $software = $collection->findSoftware($softwareName);

        if ($software === null) {
            $style->warning("Software '{$softwareName}' not found in the registry.");
            $style->text('It will be included but may fail during download.');
            $style->text('You can add custom software definitions to the registry section.');

            return DownloadConfig::fromSoftwareId($softwareName);
        }

        // Show software information
        $style->text("<fg=green>✓</> Found: <fg=cyan>{$software->name}</>");

        if ($software->description !== '') {
            $style->text("  Description: {$software->description}");
        }

        if ($software->homepage !== null) {
            $style->text("  Homepage: <fg=blue>{$software->homepage}</>");
        }

        foreach ($software->repositories as $repo) {
            $style->text("  Repository: <fg=blue>{$repo->type}:{$repo->uri}</>");
        }

        return DownloadConfig::fromSoftwareId($softwareName);
    }

    /**
     * Creates a mapping of available software for selection.
     *
     * @param SoftwareCollection $collection Software collection
     *
     * @return array<non-empty-string, Software> Map of software ID to configuration
     */
    private function getAvailableSoftwareChoices(SoftwareCollection $collection): array
    {
        $choices = [];

        /** @var Software $software */
        foreach ($collection as $software) {
            $choices[$software->getId()] = $software;
        }

        \ksort($choices);

        return $choices;
    }

    /**
     * Shows interactive software selection menu as fallback.
     *
     * @param InputInterface $input Command input
     * @param OutputInterface $output Command output
     * @param StyleInterface $style Console style helper
     * @param array<non-empty-string, Software> $availableSoftware Available software options
     *
     * @return list<DownloadConfig> Selected download actions
     */
    private function showSoftwareMenu(
        InputInterface $input,
        OutputInterface $output,
        StyleInterface $style,
        array $availableSoftware,
    ): array {
        $style->section('Available Software Packages');

        $softwareOptions = [];
        foreach ($availableSoftware as $id => $software) {
            $description = $software->description !== '' ? " - {$software->description}" : '';
            $softwareOptions[] = "{$id}{$description}";
        }

        $question = new ChoiceQuestion(
            'Select software packages (separate multiple choices with commas):',
            $softwareOptions,
        );
        $question->setMultiselect(true);

        $helper = $this->getHelper('question');
        $selectedOptions = $helper->ask($input, $output, $question);

        if ($selectedOptions === null) {
            return [];
        }

        $actions = [];
        foreach ($selectedOptions as $option) {
            // Extract software ID from "id - description" format
            $softwareId = \explode(' -', $option)[0];
            $cleanId = \trim($softwareId);

            if ($cleanId !== '') {
                $actions[] = DownloadConfig::fromSoftwareId($cleanId);
            }
        }

        return $actions;
    }

    /**
     * Generates the XML configuration file.
     *
     * @param Path $configPath Target file path
     * @param list<DownloadConfig> $downloadActions Download actions to include
     */
    private function generateConfigFile(Path $configPath, array $downloadActions): void
    {
        $builder = new ConfigBuilder();
        $xml = $builder
            ->withDownloadActions($downloadActions)
            ->build();

        \file_put_contents((string) $configPath, $xml);
    }
}
