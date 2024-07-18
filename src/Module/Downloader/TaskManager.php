<?php

declare(strict_types=1);

namespace Internal\DLoad\Module\Downloader;

use Internal\DLoad\Service\Logger;

final class TaskManager
{
    /** @var array<\Fiber> */
    private array $tasks = [];

    public function __construct(
        private Logger $logger,
    ) {}

    public function addTask(\Closure $callback): void
    {
        $this->tasks[] = new \Fiber($callback);
    }

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
                $this->logger->exception($e);
                unset($this->tasks[$key]);
                yield $e;
            }
        }

        goto start;
    }

    public function await(): void
    {
        $processor = $this->getProcessor();
        $processor->current();
        while ($processor->valid()) {
            $processor->send(null);
        }
    }
}
