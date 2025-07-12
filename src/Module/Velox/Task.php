<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\Velox;

use Internal\DLoad\Module\Config\Schema\Action\Velox as VeloxAction;
use Internal\DLoad\Module\Task\Progress;
use React\Promise\PromiseInterface;

/**
 * Represents an executable build task.
 *
 * Encapsulates all information needed to execute a build operation,
 * including configuration, progress reporting, and the actual build handler.
 * Designed to work with the existing TaskManager for async execution.
 *
 * @internal
 */
final class Task
{
    /**
     * Creates a new build task.
     *
     * @param VeloxAction $config Build configuration
     * @param \Closure(Progress): mixed $onProgress Progress callback
     * @param \Closure(): PromiseInterface<Result> $handler Build execution handler
     * @param string $name Optional task name for identification
     */
    public function __construct(
        public readonly VeloxAction $config,
        public readonly \Closure $onProgress,
        public readonly \Closure $handler,
        public readonly string $name = 'velox-build',
    ) {}

    /**
     * Executes the build task.
     *
     * @return PromiseInterface<Result> Promise that resolves to build result
     */
    public function execute(): PromiseInterface
    {
        return ($this->handler)();
    }

    /**
     * Reports progress to the registered callback.
     *
     * @param Progress $progress Current progress state
     */
    public function reportProgress(Progress $progress): void
    {
        ($this->onProgress)($progress);
    }

    /**
     * Returns a unique identifier for this task.
     *
     * @return string Task identifier
     */
    public function getId(): string
    {
        return \sprintf('%s-%s', $this->name, \spl_object_hash($this));
    }

    /**
     * Returns task configuration summary.
     *
     * @return string Human-readable task description
     */
    public function getDescription(): string
    {
        $pluginCount = \count($this->config->plugins);
        $configSource = $this->config->configFile === null ? 'API config' : 'local config';

        return \sprintf(
            'Build RoadRunner with %d plugins using %s',
            $pluginCount,
            $configSource,
        );
    }
}
