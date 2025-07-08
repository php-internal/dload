<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\Task;

use Internal\DLoad\Service\Logger;

/**
 * Task Manager Service
 *
 * Manages async tasks with Fiber-based coroutines. Tasks are executed concurrently
 * and can be suspended/resumed.
 *
 * ```php
 * // Create a task that can be suspended and resumed
 * $task = function () {
 *     // First part of execution
 *     \Fiber::suspend();
 *     // Execution continues after resume
 * };
 *
 * $taskManager = new TaskManager($logger);
 * $taskManager->addTask($task);
 *
 * // Await for all tasks to finish
 * $taskManager->await();
 * ```
 */
final class Manager
{
    /** @var array<\Fiber> Active fiber tasks */
    private array $tasks = [];

    /**
     * Constructor
     *
     * @param Logger $logger Error logging service
     */
    public function __construct(
        private readonly Logger $logger,
    ) {}

    /**
     * Adds a new task to the execution queue
     *
     * @param \Closure $callback Task implementation
     */
    public function addTask(\Closure $callback): void
    {
        $this->tasks[] = new \Fiber($callback);
    }

    /**
     * Creates a task processor generator
     *
     * Returns a generator that manages the execution cycle of all registered tasks.
     * Each yield represents a step in task execution.
     *
     * @return \Generator Task processing generator
     */
    public function getProcessor(): \Generator
    {
        start:
        if ($this->tasks === []) {
            return;
        }

        foreach ($this->tasks as $key => $task) {
            try {
                if ($task->isTerminated()) {
                    unset($this->tasks[$key]);
                    continue;
                }

                if (!$task->isStarted()) {
                    yield $task->start();
                    continue;
                }

                yield $task->resume();
            } catch (\Throwable $e) {
                $this->logger->error($e->getMessage());
                $this->logger->exception($e);
                unset($this->tasks[$key]);
                yield $e;
            }
        }

        goto start;
    }

    /**
     * Executes all tasks until completion
     *
     * Runs the processor generator until all tasks are complete.
     * Blocks execution until all tasks are finished.
     */
    public function await(): void
    {
        $processor = $this->getProcessor();
        $processor->current();
        while ($processor->valid()) {
            $processor->next();
        }
    }
}
