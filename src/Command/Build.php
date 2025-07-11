<?php

declare(strict_types=1);

namespace Internal\DLoad\Command;

use Internal\DLoad\Module\Common\FileSystem\Path;
use Internal\DLoad\Module\Config\Schema\Action\Velox as VeloxAction;
use Internal\DLoad\Module\Config\Schema\Actions;
use Internal\DLoad\Module\Task\Progress;
use Internal\DLoad\Module\Velox\Builder;
use Internal\DLoad\Module\Velox\Exception\Build as BuildException;
use Internal\DLoad\Module\Velox\Exception\Config as ConfigException;
use Internal\DLoad\Module\Velox\Exception\Dependency as DependencyException;
use Internal\DLoad\Module\Velox\Task;
use React\Promise\PromiseInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\StyleInterface;

use function React\Async\await;
use function React\Promise\all;

/**
 * Builds custom software binaries using build tools like Velox.
 *
 * Executes build actions defined in the configuration file to create
 * custom binaries with specific plugins or features.
 *
 * ```bash
 * # Build using configuration file
 * ./vendor/bin/dload build
 *
 * # Build with specific config file
 * ./vendor/bin/dload build --config=./custom-dload.xml
 * ```
 *
 * @internal
 */
#[AsCommand(
    name: 'build',
    description: 'Build custom software binaries',
)]
final class Build extends Base
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        parent::execute($input, $output);

        /** @var StyleInterface $style */
        $style = $this->container->get(StyleInterface::class);

        /** @var Actions $actionsConfig */
        $actionsConfig = $this->container->get(Actions::class);

        if ($actionsConfig->veloxBuilds === []) {
            $style->warning('No build actions found in configuration file.');
            $style->text('Add <velox> actions to your dload.xml to build custom binaries.');
            return Command::SUCCESS;
        }

        /** @var Builder $builder */
        $builder = $this->container->get(Builder::class);

        /** @var list<PromiseInterface> $actions */
        $actions = [];
        foreach ($actionsConfig->veloxBuilds as $veloxAction) {
            $actions[] = $this->prepareBuildAction($builder, $veloxAction, static fn(Progress $progress) => null);
        }

        await(all($actions));

        \count($actions) > 1 and $this->logger->info('All build actions completed.');
        return Command::SUCCESS;
    }

    /**
     * Gets the destination path as a Path object.
     */
    private function getDestinationPath(InputInterface $input): Path
    {
        /** @var string $pathOption */
        $pathOption = $input->getOption('path');
        return Path::create($pathOption);
    }

    /**
     * Executes a single Velox build action.
     *
     * @param callable(Progress): void $onProgress Callback to report progress
     */
    private function prepareBuildAction(
        Builder $builder,
        VeloxAction $veloxAction,
        callable $onProgress,
    ): PromiseInterface {
        $task = $builder->build($veloxAction, $onProgress);

        $this->logger->info('Starting build: %s', $task->name);

        // Execute the build
        return $task->execute()->then(onRejected: fn(\Throwable $e) => $this->processException($e, $task));
    }

    /**
     * Processes exceptions that occur during the build process.
     *
     * This method can be overridden to handle specific exceptions
     * or perform additional logging.
     *
     * @param \Throwable $e The exception that occurred
     * @param Task $task The task that was being executed when the exception occurred
     */
    private function processException(\Throwable $e, Task $task): void
    {
        $this->logger->error('Build task failed: %s', $task->name);

        if ($e instanceof ConfigException) {
            $this->logger->error('Configuration error: %s', $e->getMessage());
            return;
        }

        if ($e instanceof DependencyException) {
            $this->logger->error('Dependency error: %s', $e->getMessage());
            return;
        }

        if ($e instanceof BuildException) {
            $this->logger->error('Build error: %s', $e->getMessage());
            if ($e->buildOutput !== null) {
                $this->logger->info('Build Output:');
                $this->logger->print($e->buildOutput);
            }

            return;
        }

        $this->logger->exception($e);
    }
}
