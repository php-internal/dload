<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\Task;

use Internal\DLoad\Service\Logger;
use React\Promise\Deferred;
use React\Promise\PromiseInterface;

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
    /** @var array{\Fiber, Deferred} Active fiber tasks */
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
     * @template TResult
     *
     * @param \Closure(): TResult $callback Task implementation
     *
     * @return PromiseInterface<TResult>
     */
    public function addTask(\Closure $callback): PromiseInterface
    {
        $deferred = new Deferred();
        $this->tasks[] = [new \Fiber($callback), $deferred];
        return $deferred->promise();
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

        /**
         * @var \Fiber $task
         * @var Deferred $deferred
         */
        foreach ($this->tasks as $key => [$task, $deferred]) {
            try {
                if ($task->isTerminated()) {
                    unset($this->tasks[$key]);
                    $deferred->resolve($task->getReturn());
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
                $deferred->reject($e);
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
